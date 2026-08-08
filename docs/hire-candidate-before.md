# Hire Candidate — before implementation

## Existing state

- Accepted offers reserve one vacancy through `reservation_status=reserved`.
- Applications already support the terminal `hired` stage and an optional hired employee reference.
- Employee assignment and draft contract domain services already enforce tenant references, allocation, manager cycles, date and salary rules.
- Onboarding had query-side screens but no atomic recruitment-to-onboarding integration.
- No durable idempotency marker connected offer, employee and onboarding case.
- No dedicated `candidate-offer.hire` authorization or hire form.

## Required boundary

The hire operation must be one database transaction. It must lock the offer, application, candidate, requisition, position and organization unit, then create the employee, primary assignment, draft contract and onboarding records before consuming the reservation. Events may only leave the transaction after commit.

## External integrations

Identity provisioning, e-signature, calendar and candidate portal providers remain `NOT_CONNECTED`. The command creates internal pending work items only and never sends credentials or secrets.
