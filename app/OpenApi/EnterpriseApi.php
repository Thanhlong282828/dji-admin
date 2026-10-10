<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Schema Enterprise API',
    description: 'Environment-safe documentation for the application API routes that are currently registered.',
)]
#[OA\Server(url: '/', description: 'Current configured application origin')]
#[OA\Tag(name: 'Content', description: 'Public article reads')]
#[OA\Tag(name: 'Company', description: 'Company administration API')]
#[OA\Tag(name: 'Organization', description: 'Tenant-scoped organization reads')]
#[OA\Tag(name: 'Integration', description: 'Signed inbound integration boundary')]
#[OA\Tag(name: 'Onboarding', description: 'Deprecated API bridge; use the CQRS web boundary')]
#[OA\Tag(name: 'Performance', description: 'Legacy redirect bridge to the enterprise performance UI')]
#[OA\Tag(name: 'Recruitment', description: 'Deprecated API bridge; use the CQRS web boundary')]
#[OA\Tag(name: 'Tax', description: 'Authenticated, tenant-scoped Tax module API')]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'CompanyCreateRequest',
    required: ['code', 'name', 'country_code', 'timezone', 'currency_code', 'status'],
    properties: [
        new OA\Property(property: 'code', type: 'string', maxLength: 30, example: 'DEMO-COMPANY'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Demo Company'),
        new OA\Property(property: 'country_code', type: 'string', maxLength: 2, example: 'VN'),
        new OA\Property(property: 'timezone', type: 'string', maxLength: 64, example: 'Asia/Ho_Chi_Minh'),
        new OA\Property(property: 'currency_code', type: 'string', maxLength: 3, example: 'VND'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive'], example: 'active'),
    ],
    type: 'object',
)]
final class EnterpriseApi
{
    #[OA\Get(path: '/api/v1/articles', operationId: 'articleIndex', tags: ['Content'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 200, description: 'Paginated published articles')])]
    public function articleIndex(): void {}

    #[OA\Get(path: '/api/v1/articles/{slug}', operationId: 'articleShow', tags: ['Content'], parameters: [new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Published article'), new OA\Response(response: 404, description: 'Article not found')])]
    public function articleShow(): void {}

    #[OA\Get(path: '/api/v1/companies', operationId: 'companyIndex', security: [['passport' => []]], tags: ['Company'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)), new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Paginated companies'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden')])]
    public function companyIndex(): void {}

    #[OA\Post(path: '/api/v1/companies', operationId: 'companyStore', security: [['passport' => []]], tags: ['Company'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CompanyCreateRequest')), responses: [new OA\Response(response: 201, description: 'Company created'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    public function companyStore(): void {}

    #[OA\Get(path: '/api/v1/organization/tree', operationId: 'organizationTree', security: [['passport' => []]], tags: ['Organization'], parameters: [new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 200, description: 'Organization tree'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Company not found'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    public function organizationTree(): void {}

    #[OA\Get(path: '/api/v1/taxes', operationId: 'taxIndex', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 200, description: 'Tenant-scoped tax resources'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    #[OA\Post(path: '/api/v1/taxes', operationId: 'taxStore', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 201, description: 'Tax resource created'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    public function taxCollection(): void {}

    #[OA\Get(path: '/api/v1/taxes/{tax}', operationId: 'taxShow', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'tax', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 200, description: 'Tenant-scoped tax resource'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Tax resource not found')])]
    #[OA\Put(path: '/api/v1/taxes/{tax}', operationId: 'taxUpdatePut', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'tax', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 200, description: 'Tax resource updated'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Tax resource not found'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    #[OA\Patch(path: '/api/v1/taxes/{tax}', operationId: 'taxUpdatePatch', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'tax', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 200, description: 'Tax resource updated'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Tax resource not found'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    #[OA\Delete(path: '/api/v1/taxes/{tax}', operationId: 'taxDestroy', security: [['passport' => []]], tags: ['Tax'], parameters: [new OA\Parameter(name: 'tax', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 204, description: 'Tax resource deleted'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Tax resource not found')])]
    public function taxMember(): void {}

    #[OA\Post(path: '/api/integrations/webhooks/{company}/{connection}/{provider}', operationId: 'integrationWebhook', tags: ['Integration'], parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'connection', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')), new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Event-ID', in: 'header', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Event-Type', in: 'header', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 202, description: 'Receipt accepted for asynchronous processing'), new OA\Response(response: 422, description: 'Integrity or validation rejected'), new OA\Response(response: 429, description: 'Rate limited')])]
    public function integrationWebhook(): void {}

    #[OA\Get(path: '/api/v1/onboardings', operationId: 'onboardingIndex', security: [['passport' => []]], tags: ['Onboarding'], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Post(path: '/api/v1/onboardings', operationId: 'onboardingStore', security: [['passport' => []]], tags: ['Onboarding'], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    public function onboardingCollection(): void {}

    #[OA\Get(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingShow', security: [['passport' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Put(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingUpdatePut', security: [['passport' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Patch(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingUpdatePatch', security: [['passport' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Delete(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingDestroy', security: [['passport' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    public function onboardingMember(): void {}

    #[OA\Get(path: '/api/v1/performances', operationId: 'performanceIndex', security: [['passport' => []]], tags: ['Performance'], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Post(path: '/api/v1/performances', operationId: 'performanceStore', security: [['passport' => []]], tags: ['Performance'], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    public function performanceCollection(): void {}

    #[OA\Get(path: '/api/v1/performances/{performance}', operationId: 'performanceShow', security: [['passport' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Put(path: '/api/v1/performances/{performance}', operationId: 'performanceUpdatePut', security: [['passport' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Patch(path: '/api/v1/performances/{performance}', operationId: 'performanceUpdatePatch', security: [['passport' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Delete(path: '/api/v1/performances/{performance}', operationId: 'performanceDestroy', security: [['passport' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    public function performanceMember(): void {}

    #[OA\Get(path: '/api/v1/recruitments', operationId: 'recruitmentIndex', security: [['passport' => []]], tags: ['Recruitment'], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Post(path: '/api/v1/recruitments', operationId: 'recruitmentStore', security: [['passport' => []]], tags: ['Recruitment'], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    public function recruitmentCollection(): void {}

    #[OA\Get(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentShow', security: [['passport' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Put(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentUpdatePut', security: [['passport' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Patch(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentUpdatePatch', security: [['passport' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Delete(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentDestroy', security: [['passport' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    public function recruitmentMember(): void {}
}
