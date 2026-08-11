# Redis Data Loss

Symptoms: readiness 503, cache/session/queue unavailable. Impact: Tier 1 asynchronous and session degradation; MySQL business state remains authoritative. Verify Redis and queue metrics, contain duplicate workers, restore Redis, start one worker authority, verify queue depth/failed jobs and readiness. Do not recreate business mutations from guesses. Escalate if durable queued work cannot be recovered.
