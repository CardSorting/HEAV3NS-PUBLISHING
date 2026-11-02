<?php

namespace App\Services\ManualPayments;

use App\Providers\AttachmentServiceProvider;
use App\Support\MigrationSchema;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Forward-only transfer of receipt evidence, without financial observers or deleting source files. */
class DepositReceiptMigration
{
    public function up(): void
    {
        if (!MigrationSchema::hasTable('attachments') || !MigrationSchema::hasColumn('attachments', 'payment_request_id')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('manual_payment_methods')->where('direction', 'deposit')->lockForUpdate()
                ->chunkById(100, function ($methods): void {
                    foreach ($methods as $method) {
                        $settings = json_decode($method->submission_settings ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                        $fields = json_decode($method->request_fields ?? '[]', true, 512, JSON_THROW_ON_ERROR);
                        $converted = ManualPaymentDefaults::withReceiptFields($fields, (int) ($settings['minimum_attachments'] ?? 0));
                        if ($converted !== $fields) {
                            DB::table('manual_payment_methods')->where('id', $method->id)
                                ->update(['request_fields' => json_encode($converted, JSON_THROW_ON_ERROR)]);
                        }
                    }
                });
        });

        DB::table('attachments')->whereNotNull('payment_request_id')->chunkById(100, function ($receipts): void {
            foreach ($receipts as $receipt) {
                $this->transfer($receipt);
            }
        });

        // Never drop the source association if any evidence still needs transferring.
        if (DB::table('attachments')->whereNotNull('payment_request_id')->exists()) {
            throw new RuntimeException('Deposit receipts remain unmigrated. Keep the site in maintenance and retry the upgrade.');
        }
        $foreignKeys = Schema::getForeignKeys('attachments');
        Schema::table('attachments', function (Blueprint $table) use ($foreignKeys): void {
            foreach ($foreignKeys as $foreignKey) {
                if (in_array('payment_request_id', $foreignKey['columns'], true)) {
                    $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']);
                }
            }
            $table->dropColumn('payment_request_id');
        });
    }

    private function transfer(object $receipt): void
    {
        $request = $this->assertReceipt($receipt);
        $sourceName = AttachmentServiceProvider::getStorageProviderName($receipt->driver ?? 0);
        if (!$sourceName) {
            throw new RuntimeException('Receipt '.$receipt->id.' uses an unknown storage driver. Repair it and retry; its original record has been retained.');
        }
        $source = Storage::disk($sourceName);
        $target = Storage::disk(ManualPaymentDocuments::DISK);
        $sourcePath = ltrim((string) $receipt->filename, '/');
        if ($sourcePath === '' || preg_match('~(^|/)\.\.(/|$)|\\\\~', $sourcePath)) {
            throw new RuntimeException('Receipt '.$receipt->id.' has an unsafe storage path. Repair it and retry.');
        }
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (strlen($extension) > 8) {
            throw new RuntimeException('Receipt '.$receipt->id.' has an unsupported filename. Repair it and retry.');
        }
        // A stable private key makes interrupted transfers safe to retry without duplicates.
        $path = 'imports/deposit-receipts/'.hash('sha256', (string) $receipt->id).($extension ? '.'.$extension : '');
        [$hash, $size] = $this->checksum($source, $sourcePath);
        if ($size > 4294967295) {
            throw new RuntimeException('Receipt '.$receipt->id.' exceeds the document storage size limit. Its original record has been retained.');
        }
        $existing = DB::table('manual_payment_documents')->where('path', $path)->first();
        if (!$existing) {
            $stream = $source->readStream($sourcePath);
            if (!is_resource($stream)) {
                throw new RuntimeException('Receipt '.$receipt->id.' could not be read. Its original record has been retained.');
            }
            try {
                if (!$target->put($path, $stream, ['visibility' => 'private'])) {
                    throw new RuntimeException('Receipt '.$receipt->id.' could not be copied to private storage. Retry the upgrade.');
                }
            } finally {
                fclose($stream);
            }
        }
        if ([$hash, $size] !== $this->checksum($target, $path)) {
            throw new RuntimeException('Receipt '.$receipt->id.' failed copy verification. Its original file and association have been retained.');
        }

        DB::transaction(function () use ($receipt, $request, $target, $path, $extension, $size, $existing): void {
            $locked = DB::table('attachments')->where('id', $receipt->id)->lockForUpdate()->first();
            if (!$locked || $locked->payment_request_id !== $receipt->payment_request_id
                || $locked->filename !== $receipt->filename || $locked->user_id !== $receipt->user_id
                || ($locked->driver ?? 0) !== ($receipt->driver ?? 0)) {
                throw new RuntimeException('Receipt '.$receipt->id.' changed during transfer. Keep the site in maintenance and retry.');
            }
            $currentRequest = DB::table('payment_requests')->where('id', $request->id)->lockForUpdate()->first();
            if (!$currentRequest || $currentRequest->user_id !== $request->user_id || $currentRequest->transaction_id !== $request->transaction_id) {
                throw new RuntimeException('Deposit request '.$request->id.' changed during receipt transfer. Keep the site in maintenance and retry.');
            }
            $this->assertReceipt($locked);
            if ($existing) {
                if ((int) $existing->payment_request_id !== (int) $request->id || (int) $existing->user_id !== (int) $request->user_id) {
                    throw new RuntimeException('Receipt '.$receipt->id.' conflicts with an existing imported document. Its original association has been retained.');
                }
            } else {
                $methodId = DB::table('transactions')->where('id', $request->transaction_id)->value('manual_method_id');
                DB::table('manual_payment_documents')->insert([
                    'user_id' => $request->user_id, 'manual_method_id' => $methodId,
                    'payment_request_id' => $request->id, 'withdrawal_id' => null,
                    'field_key' => 'receipt', 'field_label' => 'Payment receipt',
                    'original_name' => Crypt::encryptString(ManualPaymentDocuments::safeName($receipt->filename)),
                    'path' => $path, 'extension' => $extension,
                    // Imported files are downloads unless their actual bytes are a supported preview format.
                    'mime_type' => $this->previewMime($target, $path), 'size' => $size,
                    'submitted_at' => $request->created_at ?? $receipt->created_at ?? now(),
                    'created_at' => $receipt->created_at ?? now(), 'updated_at' => $receipt->updated_at ?? now(),
                ]);
            }
            // Bypass media deletion observers: source objects remain a recoverable storage backup.
            DB::table('attachments')->where('id', $receipt->id)->delete();
        });
    }

    private function assertReceipt(object $receipt): object
    {
        $request = DB::table('payment_requests')->where('id', $receipt->payment_request_id)->first();
        if (!$request || $request->type !== 'deposit' || (int) $request->user_id !== (int) $receipt->user_id
            || !DB::table('users')->where('id', $request->user_id)->exists()) {
            throw new RuntimeException('Receipt '.$receipt->id.' has an invalid deposit owner. Repair it and retry; its original association has been retained.');
        }
        foreach (['sound_id', 'vaulted_at', 'post_id', 'message_id', 'message_template_id', 'story_id', 'reel_id'] as $column) {
            if (($receipt->{$column} ?? null) !== null) {
                throw new RuntimeException('Receipt '.$receipt->id.' is also assigned to media. Resolve the conflicting usage before retrying.');
            }
        }
        if (MigrationSchema::hasTable('attachment_placements') && DB::table('attachment_placements')->where('attachment_id', $receipt->id)->exists()) {
            throw new RuntimeException('Receipt '.$receipt->id.' has social placements. Resolve the conflicting usage before retrying.');
        }

        return $request;
    }

    /** @return array{string, int} */
    private function checksum(FilesystemAdapter $disk, string $path): array
    {
        $stream = $disk->readStream($path);
        if (!is_resource($stream)) {
            throw new RuntimeException('A deposit receipt could not be read or verified. Restore its source file and retry; the original association has been retained.');
        }
        try {
            $context = hash_init('sha256');
            $size = hash_update_stream($context, $stream);
            if (!feof($stream)) {
                throw new RuntimeException('A deposit receipt stream was interrupted. Retry the upgrade; the original association has been retained.');
            }

            return [hash_final($context), $size];
        } finally {
            fclose($stream);
        }
    }

    private function previewMime(FilesystemAdapter $disk, string $path): string
    {
        $mime = $disk->mimeType($path);
        if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
            $image = @getimagesize($disk->path($path));

            return $image && $image['mime'] === $mime ? $mime : 'application/octet-stream';
        }
        if ($mime === 'application/pdf') {
            return 'application/pdf';
        }

        return 'application/octet-stream';
    }
}
