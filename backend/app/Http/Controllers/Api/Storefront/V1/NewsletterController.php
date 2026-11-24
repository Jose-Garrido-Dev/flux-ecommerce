<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Marketing\Services\CampaignService;
use App\Domain\Marketing\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Newsletter: suscripción con doble opt-in, confirmación, baja y registro de clics de campañas. */
class NewsletterController extends StorefrontController
{
    public const SUBSCRIBE_MESSAGE = 'Te enviamos un correo para confirmar tu suscripción. Revisa tu bandeja de entrada (y la de correo no deseado).';

    public function __construct(private readonly NewsletterService $newsletter) {}

    /** La respuesta es la misma exista o no el correo, para no revelar quién está suscrito. */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'name' => ['nullable', 'string', 'max:120'],
            'consent' => ['accepted'],
            'source' => ['nullable', 'in:footer,checkout,account'],
        ], ['consent.accepted' => 'Debes aceptar recibir correos de la tienda.']);
        $this->newsletter->subscribe($data['email'], $data['name'] ?? null, $data['source'] ?? 'footer', self::clientIp($request), $this->customer($request));

        return response()->json(['data' => ['message' => self::SUBSCRIBE_MESSAGE]], 202);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:100']]);
        if (! $this->newsletter->confirm($data['token'], self::clientIp($request))) {
            return $this->validationProblem('token', 'El enlace no es válido o ya venció. Suscríbete otra vez para recibir uno nuevo.');
        }

        return response()->json(['data' => ['message' => '¡Listo! Tu suscripción quedó confirmada.']]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:400']]);
        $subscriber = $this->newsletter->fromUnsubscribeToken($data['token']);
        if (! $subscriber) {
            return $this->validationProblem('token', 'El enlace de baja no es válido.');
        }
        if ($subscriber->status !== 'unsubscribed') {
            $this->newsletter->unsubscribe($subscriber, 'enlace', self::clientIp($request));
        }

        return response()->json(['data' => ['message' => 'Te diste de baja. No te enviaremos más correos de novedades.']]);
    }

    /** Baja en un clic desde el botón del programa de correo (RFC 8058). */
    public function oneClick(Request $request, string $token): Response
    {
        $subscriber = $this->newsletter->fromUnsubscribeToken($token);
        if ($subscriber && $subscriber->status !== 'unsubscribed') {
            $this->newsletter->unsubscribe($subscriber, 'un_clic', self::clientIp($request));
        }

        return response()->noContent($subscriber ? 200 : 404);
    }

    public function click(string $token, CampaignService $campaigns): RedirectResponse|JsonResponse
    {
        $url = $campaigns->registerClick($token);

        return $url ? redirect()->away($url, 302) : $this->problem(404, 'not_found', 'El enlace no es válido.');
    }

    private function validationProblem(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
