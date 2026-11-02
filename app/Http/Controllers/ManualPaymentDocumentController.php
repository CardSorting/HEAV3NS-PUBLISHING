<?php

namespace App\Http\Controllers;

use App\Model\ManualPaymentDocument;
use App\Services\ManualPayments\ManualPaymentDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManualPaymentDocumentController extends Controller
{
    public function store(Request $request, ManualPaymentDocuments $documents)
    {
        $data = $request->validate([
            'manual_method_id' => ['required', 'integer', 'min:1'],
            'field_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{0,63}$/D'],
            'file' => ['required', 'file', 'max:5120'],
        ]);
        $document = $documents->upload($request->user()->id, (int) $data['manual_method_id'], $data['field_key'], $request->file('file'));

        return response()->json(['id' => $document->id, 'name' => $document->original_name,
            'url' => route('manual-payment-documents.download', $document)], 201);
    }

    public function download(Request $request, ManualPaymentDocument $document, ManualPaymentDocuments $documents)
    {
        abort_unless($documents->canDownload($request->user(), $document), 403);
        $disk = Storage::disk(ManualPaymentDocuments::DISK);
        abort_unless($disk->exists($document->path), 404);

        if ($request->boolean('preview')) {
            // Only validated images and PDFs may render inline; spreadsheets remain downloads.
            abort_unless($document->canPreview(), 415);

            return $disk->response($document->path, ManualPaymentDocuments::safeName($document->original_name), [
                'Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
            ], 'inline');
        }

        return $disk->download($document->path, ManualPaymentDocuments::safeName($document->original_name), [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }

    public function destroy(Request $request, ManualPaymentDocument $document, ManualPaymentDocuments $documents)
    {
        $documents->removePending($document->id, $request->user()->id);

        return response()->noContent();
    }
}
