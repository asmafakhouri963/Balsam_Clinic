<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // أخطاء التحقق من البيانات: 422
        $exceptions->render(function (
            ValidationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'بيانات الطلب غير صالحة.',
                'errors' => $exception->errors(),
            ], 422);
        });

        // أخطاء المصادقة والتوكن: 401
        $exceptions->render(function (
            AuthenticationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً باستخدام رمز وصول صالح.',
                'errors' => (object) [],
            ], 401);
        });

        // أخطاء الصلاحيات والوصول للفرع: 403
        $exceptions->render(function (
            AuthorizationException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'ليس لديك صلاحية لتنفيذ هذا الإجراء في عيادات بلسم.',
                'errors' => (object) [],
            ], 403);
        });

        // عدم العثور على سجل أو موعد أو مريض: 404
        $exceptions->render(function (
            ModelNotFoundException $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'السجل أو الموعد المطلوب غير موجود.',
                'errors' => (object) [],
            ], 404);
        });

        // بقية أخطاء HTTP مثل 404 و405:
        $exceptions->render(function (
            HttpExceptionInterface $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();

            $message = match ($status) {
                404 => $exception instanceof NotFoundHttpException
                    ? 'السجل أو المسار المطلوب غير موجود.'
                    : 'العنصر المطلوب غير موجود.',
                405 => 'طريقة الطلب غير مسموح بها لهذا المسار.',
                403 => $exception->getMessage() ?: 'غير مصرح لك بتنفيذ هذا الإجراء.',
                default => $exception->getMessage() ?: 'تعذر تنفيذ الطلب.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) [],
            ], $status);
        });

        // الأخطاء غير المتوقعة في الخادم: 500
        $exceptions->render(function (
            \Throwable $exception,
            Request $request
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = config('app.debug')
                ? ($exception->getMessage() ?: 'حدث خطأ داخلي في الخادم.')
                : 'حدث خطأ داخلي في الخادم.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => (object) [],
            ], 500);
        });
    })->create();