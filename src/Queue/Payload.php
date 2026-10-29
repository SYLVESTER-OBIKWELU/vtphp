<?php

declare(strict_types=1);

namespace VtPhp\Queue;

/**
 * Turns a job into the JSON stored on the queue and back.
 *
 * The serialized job is signed with APP_KEY (HMAC-SHA256) and checked before
 * it's unserialized, so someone who can write to the jobs table still can't
 * make the worker build objects of their choosing.
 */
final class Payload
{
    public function __construct(private readonly string $key)
    {
    }

    public function encode(Job $job): string
    {
        $serialized = base64_encode(serialize($job));

        return json_encode([
            'uuid' => self::uuid(),
            'displayName' => $job->displayName(),
            'job' => $serialized,
            'signature' => $this->sign($serialized),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @throws InvalidPayloadException when the payload is malformed or its signature doesn't match
     */
    public function decode(string $payload): Job
    {
        $data = self::parse($payload);
        $serialized = $data['job'] ?? null;
        $signature = $data['signature'] ?? null;

        if (!is_string($serialized) || !is_string($signature) || !hash_equals($this->sign($serialized), $signature)) {
            throw new InvalidPayloadException('The job payload has an invalid signature. Was APP_KEY changed, or the jobs table edited?');
        }

        $job = unserialize((string) base64_decode($serialized, true));

        if (!$job instanceof Job) {
            throw new InvalidPayloadException('The job payload does not contain a job.');
        }

        return $job;
    }

    /**
     * The readable parts of a payload (uuid, displayName), without running anything.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $payload): array
    {
        $data = json_decode($payload, true);

        return is_array($data) ? $data : [];
    }

    private function sign(string $serialized): string
    {
        return hash_hmac('sha256', $serialized, $this->key);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
