<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class MediaController extends Controller
{
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

        $isAllowedPath = str_starts_with($relativePath, 'uploads/products/');
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
}
