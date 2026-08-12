<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

final class ApiErrorResponse
{
    /**
     * @param  array<string, array<int, string>>  $details
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        array $details = [],
    ): JsonResponse {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }

    public static function fromStatus(int $status): JsonResponse
    {
        [$code, $message] = match ($status) {
            400 => ['bad_request', 'Не удалось обработать запрос.'],
            401 => ['unauthenticated', 'Требуется аутентификация.'],
            403 => ['forbidden', 'Недостаточно прав для выполнения этого действия.'],
            404 => ['not_found', 'Запрошенный ресурс не найден.'],
            405 => ['method_not_allowed', 'Этот HTTP-метод недопустим для запрошенного ресурса.'],
            409 => ['conflict', 'Запрос конфликтует с текущим состоянием ресурса.'],
            422 => ['unprocessable_entity', 'Не удалось обработать запрос.'],
            429 => ['too_many_requests', 'Слишком много запросов. Повторите попытку позже.'],
            default => ['http_error', 'Не удалось обработать запрос.'],
        };

        return self::make($code, $message, $status);
    }
}
