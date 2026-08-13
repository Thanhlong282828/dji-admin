<?php

namespace App\Support\Broadcasting;

use App\Support\RuntimeConfiguration\FeatureFlag;
use App\Support\RuntimeConfiguration\RuntimeConfigurationService;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster as BroadcasterContract;
use Psr\Log\LoggerInterface;

final class FailOpenRealtimeBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function __construct(
        private readonly BroadcasterContract $transport,
        private readonly RuntimeConfigurationService $configuration,
        private readonly LoggerInterface $logger,
    ) {}

    public function auth($request): mixed
    {
        $channelName = $request->string('channel_name')->toString();

        if ($channelName === '' || ($this->isGuardedChannel($channelName) && ! $this->retrieveUser($request, $this->normalizeChannelName($channelName)))) {
            abort(403);
        }

        return $this->verifyUserCanAccessChannel($request, $this->normalizeChannelName($channelName));
    }

    public function validAuthenticationResponse($request, $result): mixed
    {
        return $this->transport->validAuthenticationResponse($request, $result);
    }

    /**
     * Realtime UI delivery is a non-critical side effect. Only transport-level
     * BroadcastException failures are isolated; programming, domain and database
     * exceptions continue to propagate normally.
     *
     * @param  array<int, mixed>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if (! $this->configuration->enabled(FeatureFlag::RealtimeReverb)) {
            return;
        }

        try {
            $this->transport->broadcast($channels, $event, $payload);
        } catch (BroadcastException $exception) {
            $this->logger->warning('Realtime broadcast delivery failed open.', [
                'component' => 'realtime_broadcast',
                'event_type' => $event,
                'failure_category' => 'transport_unavailable',
                'exception_class' => $exception::class,
            ]);
        }
    }
}
