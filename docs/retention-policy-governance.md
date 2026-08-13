# Retention Policy Governance

Retention is configurable and versioned; it is not inferred from employment termination or a generic table timestamp. Policy resolution selects the newest enabled version whose effective window contains the evaluation time.

Classification, business purpose, ownership, active relationship, dependencies, legal hold and artifact type are inputs. No period is an official legal requirement until its `source` is replaced by an approved reference and a non-null duration. Missing authority means `BLOCKED`, not a permissive default.

Policy changes must be additive versions. Historical decisions retain policy key/version and checksum. Retroactive application requires explicit authority and review.
