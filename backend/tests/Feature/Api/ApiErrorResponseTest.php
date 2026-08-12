<?php

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/v1/testing-validation', static function (): void {
            throw ValidationException::withMessages([
                'name' => ['Поле «Название» обязательно для заполнения.'],
            ]);
        });

        Route::get('/api/v1/testing-server-error', static function (): void {
            throw new RuntimeException('Internal test exception.');
        });

        Route::get('/api/v1/testing-unauthenticated', static function (): void {
            throw new AuthenticationException;
        });

        Route::get('/api/v1/testing-forbidden', static function (): void {
            throw new AuthorizationException;
        });
    }

    public function test_validation_errors_use_the_stable_api_envelope(): void
    {
        $this->postJson('/api/v1/testing-validation')
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => 'Переданные данные не прошли проверку.',
                    'details' => [
                        'name' => ['Поле «Название» обязательно для заполнения.'],
                    ],
                ],
            ]);
    }

    public function test_not_found_errors_use_the_stable_api_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJson([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Запрошенный ресурс не найден.',
                    'details' => [],
                ],
            ]);
    }

    public function test_method_not_allowed_errors_use_the_stable_api_envelope(): void
    {
        $this->postJson('/api/v1')
            ->assertMethodNotAllowed()
            ->assertExactJson([
                'error' => [
                    'code' => 'method_not_allowed',
                    'message' => 'Этот HTTP-метод недопустим для запрошенного ресурса.',
                    'details' => [],
                ],
            ]);
    }

    public function test_authentication_errors_use_the_stable_api_envelope(): void
    {
        $this->getJson('/api/v1/testing-unauthenticated')
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Требуется аутентификация.',
                    'details' => [],
                ],
            ]);
    }

    public function test_authorization_errors_use_the_stable_api_envelope(): void
    {
        $this->getJson('/api/v1/testing-forbidden')
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'Недостаточно прав для выполнения этого действия.',
                    'details' => [],
                ],
            ]);
    }

    public function test_unexpected_errors_do_not_expose_exception_details(): void
    {
        $this->getJson('/api/v1/testing-server-error')
            ->assertStatus(500)
            ->assertExactJson([
                'error' => [
                    'code' => 'internal_server_error',
                    'message' => 'Произошла непредвиденная ошибка.',
                    'details' => [],
                ],
            ]);
    }
}
