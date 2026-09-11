# CQRS Architecture Standard

## Business module ownership (final)

`Modules/Core` owns only `CommandBusInterface`, `QueryBusInterface` and their Laravel dispatch implementations. There is no central business CQRS module.

Business intent is co-located with its owner:

- Branch lifecycle: `Modules/Company/Application/Commands/Branch`.
- Seller, Store, Buyer, Cart, Checkout, Order, Payment and Fulfillment: `Modules/Commerce`.
- Product, Category, Variant and SKU catalog: `Modules/Product`.
- Inventory balance and append-only movement: `Modules/Inventory`.
- Branch Visit, POS Checkout, Receipt and POS Sales: `Modules/POS`.
- Branch finance, tax runtime and payroll reconciliation read models: `Modules/Finance`.
- Branch bonus workflow: `Modules/Payroll`.
- Executive aggregation: `Modules/Dashboard`.

The former temporary paths `app/Application/Commerce`, `app/Support/Commerce` and `app/Support/Finance` are forbidden by `BusinessModuleOwnershipTest`.

## Write flow

`Route → FormRequest/validated payload → Controller → CommandBus → CommandHandler → domain/application service → repository/DB transaction → event/audit`

Controller chỉ authorization, ánh xạ payload đã validate, dispatch và trả response. Transaction thuộc application/domain service hoặc repository. Event chỉ phát sau mutation thành công.

## Read flow

`Route → Controller → QueryBus → QueryHandler → ReadRepository/ReadService → DTO/ViewModel → Blade/JSON`

Read service được chấp nhận cho aggregate SQL phức tạp, với điều kiện read-only, tenant scoped, bounded và được ghi rõ source. Blade không query database và không tạo business total.

## Snapshot rule

- Live/mutable source có thể rebuild read model/cache theo company/branch/period.
- Finalized Payroll, paid Receipt, fulfilled Order, posted Journal và recognized Revenue không bị upstream live source viết đè.
- Correction sử dụng adjustment/reversal workflow, không hard delete hay rewrite history.

## Current enforcement

- Core `LaravelCommandBus` và `LaravelQueryBus` resolve handler theo hậu tố `Command → Handler`, `Query → Handler`.
- Company, phần lớn Employee/Payroll/Organization đã dùng buses.
- Branch Commerce writes và Seller Governance transition đã được chuyển sang commands trong audit này.
- Legacy direct-service/controller flows được theo dõi theo route matrix; không gọi một flow là CQRS chỉ vì đổi tên service.
