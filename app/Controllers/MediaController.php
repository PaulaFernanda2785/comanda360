<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\DashboardRepository;
use App\Services\Shared\SupportAttachmentService;
use RuntimeException;

final class MediaController extends Controller
{
    public function __construct(
        private readonly DashboardRepository $dashboard = new DashboardRepository(),
        private readonly SupportAttachmentService $supportAttachments = new SupportAttachmentService()
    ) {}

    public function company(Request $request): Response
    {
        $rawPath = trim((string) $request->input('path', ''));
        if ($rawPath === '') {
            return Response::make('Imagem nao informada.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $relativePath = str_replace('\\', '/', ltrim($rawPath, '/'));
        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = ltrim(substr($relativePath, strlen('public/')), '/');
        }

        $isAllowedPath = str_starts_with($relativePath, 'uploads/company/');
        $hasTraversal = str_contains($relativePath, '../') || str_contains($relativePath, '..\\');
        if (!$isAllowedPath || $hasTraversal) {
            return Response::make('Caminho de imagem invalido.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $absolutePath = BASE_PATH . '/public/' . $relativePath;
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return Response::make('Imagem nao encontrada.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $mime = @mime_content_type($absolutePath);
        if (!is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return Response::make('Falha ao ler imagem.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function product(Request $request): Response
    {
        $rawPath = trim((string) $request->input('path', ''));
        if ($rawPath === '') {
            return Response::make('Imagem nao informada.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $relativePath = str_replace('\\', '/', ltrim($rawPath, '/'));
        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = ltrim(substr($relativePath, strlen('public/')), '/');
        }

        $isAllowedPath = str_starts_with($relativePath, 'uploads/products/')
            || preg_match('#^uploads/company/\d+/products/#', $relativePath) === 1;
        $hasTraversal = str_contains($relativePath, '../') || str_contains($relativePath, '..\\');
        if (!$isAllowedPath || $hasTraversal) {
            return Response::make('Caminho de imagem invalido.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $absolutePath = BASE_PATH . '/public/' . $relativePath;
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return Response::make('Imagem nao encontrada.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $mime = @mime_content_type($absolutePath);
        if (!is_string($mime) || $mime === '') {
            $mime = 'application/octet-stream';
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return Response::make('Falha ao ler imagem.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function tableQr(Request $request): Response
    {
        $rawData = trim((string) $request->input('data', ''));
        if ($rawData === '') {
            return Response::make('Dados do QR nao informados.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (!str_starts_with($rawData, 'comanda360:')) {
            return Response::make('Formato de payload QR invalido.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (strlen($rawData) > 600) {
            return Response::make('Dados do QR excedem o limite permitido.', 400, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $size = (int) $request->input('size', 760);
        if ($size < 200) {
            $size = 200;
        }
        if ($size > 1200) {
            $size = 1200;
        }

        $cacheDir = BASE_PATH . '/storage/cache/table_qr';
        $cacheKey = hash('sha256', $rawData . '|' . $size);
        $cachePngPath = $cacheDir . '/' . $cacheKey . '.png';
        $cacheSvgPath = $cacheDir . '/' . $cacheKey . '.svg';
        $requestedFormat = strtolower(trim((string) $request->input('format', '')));

        if ($requestedFormat === 'svg') {
            try {
                $svgContent = $this->loadOrGenerateQrSvg($rawData, $size, $cacheDir, $cacheSvgPath);
                return Response::make($svgContent, 200, [
                    'Content-Type' => 'image/svg+xml; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            } catch (RuntimeException $svgError) {
                return Response::make(
                    $this->buildFailureSvg('Falha ao gerar QR em SVG.'),
                    200,
                    [
                        'Content-Type' => 'image/svg+xml; charset=UTF-8',
                        'Cache-Control' => 'no-store',
                        'X-QR-Fallback' => 'error-svg',
                    ]
                );
            }
        }

        try {
            if (!is_file($cachePngPath)) {
                if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
                    throw new RuntimeException('Nao foi possivel preparar o diretorio de cache do QR.');
                }

                $this->generateQrWithNode($rawData, $size, $cachePngPath, 'png');
            }

            $content = file_get_contents($cachePngPath);
            if ($content === false || $content === '') {
                throw new RuntimeException('Falha ao ler a imagem do QR gerada.');
            }
        } catch (RuntimeException $pngError) {
            try {
                $svgContent = $this->loadOrGenerateQrSvg($rawData, $size, $cacheDir, $cacheSvgPath);

                return Response::make($svgContent, 200, [
                    'Content-Type' => 'image/svg+xml; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=31536000',
                    'X-QR-Fallback' => 'svg',
                ]);
            } catch (RuntimeException $svgError) {
                return Response::make(
                    $this->buildFailureSvg('Falha ao gerar QR (PNG e SVG).'),
                    200,
                    [
                        'Content-Type' => 'image/svg+xml; charset=UTF-8',
                        'Cache-Control' => 'no-store',
                        'X-QR-Fallback' => 'error-svg',
                    ]
                );
            }
        }

        return Response::make($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    public function supportAttachment(Request $request): Response
    {
        $user = Auth::user() ?? [];
        if ($user === []) {
            return Response::make('Autenticacao obrigatoria.', 401, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $attachmentId = (int) $request->input('attachment_id', 0);
        $messageId = (int) $request->input('message_id', 0);
        if ($attachmentId <= 0 && $messageId <= 0) {
            return Response::make('Anexo nao informado.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $attachment = $attachmentId > 0
            ? $this->dashboard->findSupportTicketAttachmentById($attachmentId)
            : $this->dashboard->findSupportTicketMessageAttachmentById($messageId);
        if ($attachment === null) {
            return Response::make('Anexo nao encontrado.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $isSaasUser = (int) ($user['is_saas_user'] ?? 0) === 1
            || strtolower(trim((string) ($user['role_context'] ?? ''))) === 'saas';
        $sameCompany = (int) ($user['company_id'] ?? 0) > 0
            && (int) ($attachment['company_id'] ?? 0) === (int) ($user['company_id'] ?? 0);

        if (!$isSaasUser && !$sameCompany) {
            return Response::make('Acesso negado ao anexo.', 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $absolutePath = $this->supportAttachments->resolveAbsolutePath((string) ($attachment['attachment_path'] ?? ''));
        if ($absolutePath === null) {
            return Response::make('Arquivo do anexo nao encontrado no servidor.', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            return Response::make('Falha ao ler o anexo.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $mime = trim((string) ($attachment['attachment_mime_type'] ?? ''));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $downloadName = trim((string) ($attachment['attachment_original_name'] ?? 'anexo'));
        $downloadName = str_replace(["\r", "\n", '"'], [' ', ' ', "'"], $downloadName);

        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function loadOrGenerateQrSvg(string $payload, int $size, string $cacheDir, string $cacheSvgPath): string
    {
        if (!is_file($cacheSvgPath)) {
            if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
                throw new RuntimeException('Nao foi possivel preparar o diretorio de cache do QR.');
            }

            $this->generateQrWithNode($payload, $size, $cacheSvgPath, 'svg');
        }

        $svgContent = file_get_contents($cacheSvgPath);
        if ($svgContent === false || trim($svgContent) === '') {
            throw new RuntimeException('Falha ao ler o SVG de fallback.');
        }

        return $svgContent;
    }

    private function buildFailureSvg(string $title): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="760" height="760" viewBox="0 0 760 760" role="img" aria-label="Falha ao gerar QR">
  <rect x="0" y="0" width="760" height="760" fill="#ffffff" />
  <rect x="24" y="24" width="712" height="712" fill="#fff7ed" stroke="#fdba74" stroke-width="2" rx="16" />
  <text x="48" y="96" fill="#9a3412" font-size="34" font-family="Arial, sans-serif" font-weight="700">{$safeTitle}</text>
  <text x="48" y="142" fill="#7c2d12" font-size="20" font-family="Arial, sans-serif">Tente novamente em instantes.</text>
  <text x="48" y="192" fill="#7c2d12" font-size="15" font-family="Arial, sans-serif">A emissao do QR nao conseguiu ser concluida.</text>
  <text x="48" y="224" fill="#7c2d12" font-size="15" font-family="Arial, sans-serif">Se o problema persistir, consulte os logs do servidor.</text>
</svg>
SVG;
    }

    private function generateQrWithNode(string $payload, int $size, string $targetPath, string $format): void
    {
        $scriptPath = BASE_PATH . '/scripts/generate_table_qr.cjs';
        if (!is_file($scriptPath)) {
            throw new RuntimeException('Script local de geracao de QR nao encontrado.');
        }

        if ($format !== 'png' && $format !== 'svg') {
            throw new RuntimeException('Formato de geracao de QR invalido.');
        }

        $nodeBinary = $this->resolveNodeBinary();
        $command = escapeshellarg($nodeBinary) . ' ' .
            escapeshellarg($scriptPath) . ' --out ' .
            escapeshellarg($targetPath) . ' --size ' .
            (int) $size . ' --data-base64 ' .
            escapeshellarg(base64_encode($payload)) . ' --format ' .
            escapeshellarg($format);

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0 || !is_file($targetPath)) {
            $error = trim(implode("\n", $output));
            throw new RuntimeException(
                $error !== '' ? 'Falha ao gerar QR internamente: ' . $error : 'Falha ao gerar QR internamente.'
            );
        }
    }

    private function resolveNodeBinary(): string
    {
        $envNode = trim((string) getenv('NODE_BINARY'));
        if ($envNode !== '') {
            return $envNode;
        }

        $candidates = [
            'node',
            'node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
        ];

        foreach ($candidates as $candidate) {
            $probe = @shell_exec(escapeshellarg($candidate) . ' -v 2>&1');
            if (is_string($probe) && trim($probe) !== '') {
                return $candidate;
            }
        }

        throw new RuntimeException('Node.js nao encontrado no servidor para gerar QR local.');
    }
}
