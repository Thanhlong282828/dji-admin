# Deployment failure

- Symptoms: image fails readiness or required processes are unhealthy.
- Verify: immutable image digest, container health, migrations and correlation-scoped logs.
- Safe mitigation: stop promotion; preserve diagnostic evidence.
- Rollback: run the governed rollback script to the last verified immutable image.
- Recovery: all health gates pass and worker/scheduler are running.
- Escalate: schema incompatibility or rollback target unavailable.
