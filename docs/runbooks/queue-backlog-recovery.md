# Queue Backlog Recovery

Symptoms: rising depth/oldest age or stopped Horizon. Verify Redis, workers, failed jobs and poison-job isolation. Contain by pausing affected queue, restore bounded worker capacity, process oldest safe work, observe idempotency and dead letters, then verify baseline depth and no duplicate mutation. Roll back worker image when compatible; escalate persistent poison or business conflicts.
