<?php
declare(strict_types=1);

/**
 * Controller: GET /api/me
 *
 * Responsibility: receive the request, ask the service for the payload,
 * serialize the DTO to JSON.  No business logic lives here.
 */
final class MeController
{
    public function __construct(private readonly MeService $service) {}

    public function __invoke(Request $request): void
    {
        $dto = $this->service->getMePayload();
        json_response($dto->toArray());
    }
}
