"use strict";
/* global trans */
/* exported ManualPaymentUploads */

// These IDs refer to private, temporary documents; the server binds them to one request.
var ManualPaymentUploads = (() => {
    const states = new WeakMap();
    const fields = scope => Array.from((scope || document).querySelectorAll('.manual-document-field'));
    const state = field => {
        if (!states.has(field)) states.set(field, {pending: 0, submitting: false});
        return states.get(field);
    };
    const message = (field, text) => { field.querySelector('.manual-document-status').textContent = text; };
    const ids = field => Array.from(field.querySelectorAll('.manual-document-id')).map(input => input.value);
    const headers = () => ({'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'});

    async function send(url, options) {
        const fallback = trans('Upload failed. Please try again.');
        const response = await fetch(url, {credentials: 'same-origin', ...options, headers: headers()}).catch(() => {
            throw new Error(fallback);
        });
        const body = response.status === 204 ? null : await response.json().catch(() => null);
        if (!response.ok || (response.status !== 204 && !body)) {
            throw new Error(Object.values(body?.errors || {}).flat()[0] || body?.message || fallback);
        }
        return body;
    }

    async function upload(field, picker) {
        if (state(field).submitting) { picker.value = ''; return; }
        const selected = Array.from(picker.files);
        picker.value = '';
        message(field, '');
        if (ids(field).length + state(field).pending + selected.length > Number(field.dataset.maxFiles)) {
            message(field, trans('Too many files for this field.'));
            return;
        }
        const allowed = picker.accept.split(',').map(extension => extension.trim().toLowerCase());
        if (selected.some(file => !allowed.includes('.' + file.name.split('.').pop().toLowerCase()) || !file.size || file.size > Number(field.dataset.maxSize))) {
            message(field, trans('Choose an allowed file within the upload size limit.'));
            return;
        }
        // Count the full queue immediately, including files still waiting to upload.
        state(field).pending += selected.length;
        for (const file of selected) {
            const row = document.createElement('li');
            row.className = 'd-flex align-items-center gap-2 border rounded p-2 mt-2 manual-document-item';
            const label = document.createElement('span');
            label.className = 'small text-break flex-grow-1';
            label.textContent = file.name + ' — ' + trans('Uploading');
            row.append(label);
            field.querySelector('.manual-document-list').append(row);
            try {
                const data = new FormData();
                data.append('manual_method_id', field.dataset.methodId);
                data.append('field_key', field.dataset.fieldKey);
                data.append('file', file);
                const result = await send(field.dataset.uploadUrl, {method: 'POST', body: data});
                row.dataset.documentId = result.id;
                row.dataset.deleteUrl = result.url;
                const link = document.createElement('a');
                link.className = label.className;
                link.href = result.url;
                link.textContent = result.name;
                label.replaceWith(link);
                const input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'manual-document-id manual-request-field';
                input.name = 'submitted_fields[' + field.dataset.fieldKey + '][]';
                input.dataset.fieldKey = field.dataset.fieldKey;
                input.value = result.id;
                input.disabled = picker.disabled;
                if (field.dataset.form) input.setAttribute('form', field.dataset.form);
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn-close flex-shrink-0 ms-2 manual-document-remove';
                remove.title = trans('Remove');
                remove.setAttribute('aria-label', trans('Remove') + ' ' + result.name);
                row.append(input, remove);
                picker.classList.remove('is-invalid');
            } catch (error) {
                row.remove();
                message(field, error.message);
            } finally {
                state(field).pending--;
            }
        }
    }

    function validate(scope) {
        let valid = true;
        fields(scope).forEach(field => {
            const picker = field.querySelector('.manual-document-picker');
            if (picker.disabled) return;
            const waiting = state(field).pending > 0;
            const missing = field.dataset.required === '1' && !ids(field).length;
            picker.classList.toggle('is-invalid', waiting || missing);
            if (waiting || missing) {
                valid = false;
                message(field, trans(waiting ? 'Please wait for uploads to finish.' : 'Please attach the required files.'));
            }
        });
        return valid;
    }

    function setSubmitting(scope, busy) {
        fields(scope).forEach(field => {
            state(field).submitting = busy;
            field.querySelectorAll('.manual-document-remove').forEach(button => { button.disabled = busy; });
        });
    }

    function countUploaded(scope) {
        const uploadedIds = fields(scope)
            .filter(field => !field.querySelector('.manual-document-picker').disabled)
            .flatMap(ids);
        return new Set(uploadedIds).size;
    }

    function clear(scope) {
        fields(scope).forEach(field => {
            field.querySelector('.manual-document-list').replaceChildren();
            message(field, '');
        });
    }

    document.addEventListener('change', event => {
        if (event.target.matches('.manual-document-picker')) upload(event.target.closest('.manual-document-field'), event.target);
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('.manual-document-remove');
        if (!button) return;
        const field = button.closest('.manual-document-field');
        if (state(field).submitting || button.disabled) return;
        const row = button.closest('.manual-document-item');
        button.disabled = true;
        state(field).pending++;
        try {
            await send(row.dataset.deleteUrl, {method: 'DELETE'});
            row.remove();
            message(field, '');
        } catch (error) {
            button.disabled = false;
            message(field, error.message);
        } finally {
            state(field).pending--;
        }
    });
    document.addEventListener('submit', event => {
        if (event.target.id !== 'wallet-deposit') return;
        const panel = document.querySelector('.manual-method-panel:not(.d-none)');
        if (panel && (fields(panel).some(field => state(field).submitting) || !validate(panel))) event.preventDefault();
        else if (panel) setSubmitting(panel, true);
    });

    return {validate, setSubmitting, clear, ids, countUploaded};
})();
