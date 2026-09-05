<?php

declare(strict_types=1);

namespace Zohal\Sdk\Services;

use Zohal\Sdk\ZohalClient;

/**
 * Wraps the /services/biometric/* video-authentication (Liveness) flow:
 * upload a selfie video, start a Liveness session referencing it, then
 * poll (or wait on your own callback_url webhook) for the result. None
 * of these three endpoints use the usual {response_body:{data,...}}
 * envelope — fields sit directly under response_body — so every method
 * here goes through ZohalClient::postRaw()/getRaw().
 *
 * The spec's description for the media-upload endpoint mentions it's
 * called "with a dedicated token" (توکن اختصاصی), i.e. Zohal may issue a
 * separate bearer token for this video-auth service. The Bearer scheme
 * itself is identical, so nothing here needs to change — just construct
 * this class with a ZohalClient built from that dedicated token if one
 * was issued to you, instead of the client used for InquiryService etc.
 */
final class BiometricService
{
    public function __construct(private readonly ZohalClient $client)
    {
    }

    /**
     * POST /services/biometric/media/ — upload a selfie video for use in
     * a later Liveness session. Returns a media id to pass as
     * $selfieVideoMediaId to startLivenessSession().
     *
     * @return array{id: string, type: string}
     */
    public function uploadMedia(string $videoFilePath, string $type = 'selfie_video'): array
    {
        $body = $this->client->postMultipartRaw('services/biometric/media/', [
            [
                'name' => 'file',
                'contents' => fopen($videoFilePath, 'rb'),
                'filename' => basename($videoFilePath),
            ],
            [
                'name' => 'type',
                'contents' => $type,
            ],
        ]);

        return [
            'id' => $body['response_body']['id'] ?? '',
            'type' => $body['response_body']['type'] ?? '',
        ];
    }

    /**
     * POST /services/biometric/session/liveness/ — start a Liveness
     * session for the given selfie-video media id and identity details.
     * The session is created as status=pending; the final verdict
     * arrives either at $callbackUrl or via sessionResult() polling.
     *
     * @return array{session_id: string, status: string}
     */
    public function startLivenessSession(
        string $selfieVideoMediaId,
        string $nationalCode,
        string $nationalCardSerial,
        string $birthDate,
        ?string $callbackUrl = null,
    ): array {
        $payload = [
            'media' => ['selfie_video' => $selfieVideoMediaId],
            'national_code' => $nationalCode,
            'national_card_serial' => $nationalCardSerial,
            'birth_date' => $birthDate,
        ];

        if ($callbackUrl !== null) {
            $payload['callback_url'] = $callbackUrl;
        }

        $body = $this->client->postRaw('services/biometric/session/liveness/', $payload);

        return [
            'session_id' => $body['response_body']['session_id'] ?? '',
            'status' => $body['response_body']['status'] ?? '',
        ];
    }

    /**
     * GET /services/biometric/session/{id}/result — fetch the current
     * status and, once completed, the verdict of a Liveness session.
     * `reason` is one of ACCEPT, REJECT_FACE_NOT_MATCH_ID,
     * REJECT_MORE_THAN_ONE_PERSON, REJECT_NO_PERSON_DETECTED,
     * REJECT_PERSON_TOO_FAR_AWAY, REJECT_VIDEO_BAD_LIGHT,
     * REJECT_VIDEO_BAD_QUALITY, REJECT_VIDEO_NOT_LIVE, UNKNOWN, or
     * UNDEFINED; `result` is "matched", "rejected", or "" while pending.
     *
     * @return array{completed_at: ?string, reason: string, result: string, status: string, type: string}
     */
    public function sessionResult(string $sessionId): array
    {
        $body = $this->client->getRaw('services/biometric/session/' . rawurlencode($sessionId) . '/result');

        return [
            'completed_at' => $body['response_body']['completed_at'] ?? null,
            'reason' => $body['response_body']['reason'] ?? '',
            'result' => $body['response_body']['result'] ?? '',
            'status' => $body['response_body']['status'] ?? '',
            'type' => $body['response_body']['type'] ?? '',
        ];
    }
}
