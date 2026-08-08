# Leave Management foundation

Leave duration is stored in integer minutes and calculated from Employee Work Profile timezone, Work Calendar days and holidays. Model A is used: submit writes a reservation; approve releases reservation and writes usage; reject/cancel/withdraw releases it. Ledger is source of truth. Single-level approval foundation is FUNCTIONAL; multi-level approval, accrual scheduler and private attachment workflow are PARTIAL/NOT_STARTED.
