<?php
declare(strict_types=1);

namespace App\Services\Shared;

use App\Exceptions\ValidationException;

final class SupportAttachmentService
{
    private const MAX_ATTACHMENT_SIZE_BYTES = 10485760; // 10MB
    private const MAX_ATTACHMENTS_PER_MESSAGE = 8;

    private const ALLOWED_EXTENSIONS = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain', 'application/octet-stream'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    public function storeAttachments(int $companyId, int $ticketId, int $messageId, mixed $files): array
    {
        $normalizedFiles = $this->normalizeFilesInput($files);
        if ($normalizedFiles === []) {
            return [];
        }

        if ($companyId <= 0 || $ticketId <= 0 || $messageId <= 0) {
            throw new ValidationException('Contexto invalido para anexar arquivos no chamado.');
        }

        if (count($normalizedFiles) > self::MAX_ATTACHMENTS_PER_MESSAGE) {
            throw new ValidationException('Cada mensagem aceita no maximo 8 anexos.');
        }

        $stored = [];
        try {
            foreach ($normalizedFiles as $file) {
                $stored[] = $this->storeSingleAttachment($companyId, $ticketId, $messageId, $file);
            }
        } catch (\Throwable $e) {
            foreach ($stored as $attachment) {
                if (is_array($attachment)) {
                    $this->deleteAttachment((string) ($attachment['attachment_path'] ?? ''));
                }
            }
            throw $e;
        }

        return $stored;
    }

    public function storeLegacyAttachment(int $companyId, int $ticketId, ?array $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $this->storeSingleAttachment($companyId, $ticketId, 0, $file);
    }

    public function deleteAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $this->deleteAttachment((string) ($attachment['attachment_path'] ?? ''));
        }
    }

    public function isImageMimeType(?string $mimeType): bool
    {
        $normalized = strtolower(trim((string) ($mimeType ?? '')));
        return str_starts_with($normalized, 'image/');
    }

    private function storeSingleAttachment(int $companyId, int $ticketId, int $messageId, array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException($this->uploadErrorMessage($error));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $isUploadedFile = $tmpName !== '' && is_uploaded_file($tmpName);
        $isLocalEnv = strtolower((string) getenv('APP_ENV')) === 'local';
        if (!$isUploadedFile && !($isLocalEnv && $tmpName !== '' && is_file($tmpName))) {
            throw new ValidationException('Arquivo anexado invalido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_ATTACHMENT_SIZE_BYTES) {
            throw new ValidationException('O anexo deve ter ate 10MB.');
        }

        $originalName = $this->normalizeOriginalName((string) ($file['name'] ?? 'anexo'));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '' || !isset(self::ALLOWED_EXTENSIONS[$extension])) {
            throw new ValidationException('Formato de anexo nao suportado. Use imagem, PDF, TXT, CSV, DOC, DOCX, XLS, XLSX ou ZIP.');
        }

        $mimeType = $this->detectMimeType($tmpName);
        $allowedMimeTypes = self::ALLOWED_EXTENSIONS[$extension];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new ValidationException('O tipo real do anexo nao corresponde a uma extensao permitida.');
        }

        $targetPath = $this->prepareTargetPath($companyId, $ticketId, $messageId, $extension);
        $moved = move_uploaded_file($tmpName, $targetPath['absolute']);
        if (!$moved && $isLocalEnv && is_file($tmpName)) {
            $moved = @rename($tmpName, $targetPath['absolute']);
            if (!$moved) {
                $moved = @copy($tmpName, $targetPath['absolute']);
                if ($moved && is_file($tmpName)) {
                    @unlink($tmpName);
                }
            }
        }

        if (!$moved) {
            throw new ValidationException('Nao foi possivel salvar o anexo do chamado.');
        }

        return [
            'attachment_path' => $targetPath['relative'],
            'attachment_original_name' => $originalName,
            'attachment_mime_type' => $mimeType,
            'attachment_size_bytes' => $size,
        ];
    }

    public function deleteAttachment(?string $storedPath): void
    {
        $relativePath = $this->normalizeStoredPath($storedPath);
        if ($relativePath === null) {
            return;
        }

        $absolutePath = BASE_PATH . '/storage/' . $relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    public function resolveAbsolutePath(?string $storedPath): ?string
    {
        $relativePath = $this->normalizeStoredPath($storedPath);
        if ($relativePath === null) {
            return null;
        }

        $absolutePath = BASE_PATH . '/storage/' . $relativePath;
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        return $absolutePath;
    }

    private function prepareTargetPath(int $companyId, int $ticketId, int $messageId, string $extension): array
    {
        $baseDir = BASE_PATH . '/storage/support_attachments/company_' . $companyId . '/ticket_' . $ticketId;
        if ($messageId > 0) {
            $baseDir .= '/message_' . $messageId;
        } else {
            $baseDir .= '/legacy';
        }
        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new ValidationException('Nao foi possivel preparar a pasta de anexos do chamado.');
        }

        $filename = 'msg_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

        $relativeDir = 'support_attachments/company_' . $companyId . '/ticket_' . $ticketId . '/';
        $relativeDir .= $messageId > 0 ? 'message_' . $messageId : 'legacy';

        return [
            'absolute' => $baseDir . '/' . $filename,
            'relative' => $relativeDir . '/' . $filename,
        ];
    }

    private function normalizeStoredPath(?string $storedPath): ?string
    {
        $normalized = trim((string) ($storedPath ?? ''));
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $normalized), '/');
        $hasTraversal = str_contains($normalized, '../') || str_contains($normalized, '..\\');
        if ($hasTraversal || !str_starts_with($normalized, 'support_attachments/')) {
            return null;
        }

        return $normalized;
    }

    private function detectMimeType(string $path): string
    {
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
                @finfo_close($finfo);
            }
        }

        if ($mime === '') {
            $fallback = @mime_content_type($path);
            if (is_string($fallback) && $fallback !== '') {
                $mime = $fallback;
            }
        }

        return strtolower(trim($mime !== '' ? $mime : 'application/octet-stream'));
    }

    private function normalizeOriginalName(string $name): string
    {
        $basename = trim((string) basename(str_replace('\\', '/', $name)));
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return 'anexo';
        }

        $basename = preg_replace('/[\x00-\x1F\x7F]+/', '', $basename) ?? 'anexo';
        $basename = trim($basename);
        if ($basename === '') {
            return 'anexo';
        }

        if (mb_strlen($basename) > 180) {
            $basename = mb_substr($basename, 0, 180);
        }

        return $basename;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O anexo excede o limite permitido pelo servidor. Envie um arquivo menor que 10MB.',
            UPLOAD_ERR_PARTIAL => 'O upload do anexo foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_TMP_DIR => 'Servidor sem pasta temporaria para upload de anexo.',
            UPLOAD_ERR_CANT_WRITE => 'Servidor sem permissao para gravar o anexo.',
            UPLOAD_ERR_EXTENSION => 'Uma extensao do PHP bloqueou o upload do anexo.',
            default => 'Falha no envio do anexo.',
        };
    }

    private function normalizeFilesInput(mixed $files): array
    {
        if (!is_array($files) || $files === []) {
            return [];
        }

        $names = $files['name'] ?? null;
        if (!is_array($names)) {
            $error = (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE);
            return $error === UPLOAD_ERR_NO_FILE ? [] : [is_array($files) ? $files : []];
        }

        $normalized = [];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? $files['tmp_name'] : [];
        $errors = is_array($files['error'] ?? null) ? $files['error'] : [];
        $sizes = is_array($files['size'] ?? null) ? $files['size'] : [];
        $types = is_array($files['type'] ?? null) ? $files['type'] : [];

        foreach ($names as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'type' => $types[$index] ?? '',
                'tmp_name' => $tmpNames[$index] ?? '',
                'error' => $error,
                'size' => $sizes[$index] ?? 0,
            ];
        }

        return $normalized;
    }
}
