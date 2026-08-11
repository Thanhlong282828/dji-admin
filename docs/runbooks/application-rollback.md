# Application Rollback

Symptoms: candidate readiness/smoke failure. Verify previous immutable image and database/config backward compatibility. Freeze promotion, retain current evidence, switch to the approved previous image, verify live/ready, queue, scheduler and observability. Never automatically roll database migrations down; use forward-fix when compatibility fails. Escalate before resuming traffic.
