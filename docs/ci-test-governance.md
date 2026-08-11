# CI Test Governance

CI orders cheap, high-signal failures before expensive gates. Readiness/orphan checks precede static and application gates. Architecture/test-governance fitness functions precede serial concurrency. Full PHPUnit is the final regression, followed by an `if: always()` orphan postflight.

Jobs mutating MySQL must own an isolated service/database. Feature, concurrency and browser jobs may not concurrently share a schema. Retry is diagnostic only and cannot silently green the final gate.

Allowed artifacts are concise summaries, failure screenshots and redacted logs. `.env`, credentials, production dumps and PII are forbidden. Release evidence stores counts, exit code, duration, commit and checksum rather than massive logs.
