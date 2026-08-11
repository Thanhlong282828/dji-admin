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

    #[OA\Get(path: '/api/v1/companies', operationId: 'companyIndex', security: [['sanctum' => []]], tags: ['Company'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)), new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Paginated companies'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden')])]
    public function companyIndex(): void {}

    #[OA\Post(path: '/api/v1/companies', operationId: 'companyStore', security: [['sanctum' => []]], tags: ['Company'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CompanyCreateRequest')), responses: [new OA\Response(response: 201, description: 'Company created'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    public function companyStore(): void {}

    #[OA\Get(path: '/api/v1/organization/tree', operationId: 'organizationTree', security: [['sanctum' => []]], tags: ['Organization'], parameters: [new OA\Parameter(name: 'company_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))], responses: [new OA\Response(response: 200, description: 'Organization tree'), new OA\Response(response: 401, description: 'Unauthenticated'), new OA\Response(response: 403, description: 'Forbidden'), new OA\Response(response: 404, description: 'Company not found'), new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))])]
    public function organizationTree(): void {}

    #[OA\Post(path: '/api/integrations/webhooks/{company}/{connection}/{provider}', operationId: 'integrationWebhook', tags: ['Integration'], parameters: [new OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'connection', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')), new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Event-ID', in: 'header', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Event-Type', in: 'header', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'X-Signature', in: 'header', required: true, schema: new OA\Schema(type: 'string'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')), responses: [new OA\Response(response: 202, description: 'Receipt accepted for asynchronous processing'), new OA\Response(response: 422, description: 'Integrity or validation rejected'), new OA\Response(response: 429, description: 'Rate limited')])]
    public function integrationWebhook(): void {}

    #[OA\Get(path: '/api/v1/onboardings', operationId: 'onboardingIndex', security: [['sanctum' => []]], tags: ['Onboarding'], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Post(path: '/api/v1/onboardings', operationId: 'onboardingStore', security: [['sanctum' => []]], tags: ['Onboarding'], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    public function onboardingCollection(): void {}

    #[OA\Get(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingShow', security: [['sanctum' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Put(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingUpdatePut', security: [['sanctum' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Patch(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingUpdatePatch', security: [['sanctum' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    #[OA\Delete(path: '/api/v1/onboardings/{onboarding}', operationId: 'onboardingDestroy', security: [['sanctum' => []]], tags: ['Onboarding'], parameters: [new OA\Parameter(name: 'onboarding', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS onboarding routes')])]
    public function onboardingMember(): void {}

    #[OA\Get(path: '/api/v1/performances', operationId: 'performanceIndex', security: [['sanctum' => []]], tags: ['Performance'], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Post(path: '/api/v1/performances', operationId: 'performanceStore', security: [['sanctum' => []]], tags: ['Performance'], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    public function performanceCollection(): void {}

    #[OA\Get(path: '/api/v1/performances/{performance}', operationId: 'performanceShow', security: [['sanctum' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Put(path: '/api/v1/performances/{performance}', operationId: 'performanceUpdatePut', security: [['sanctum' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Patch(path: '/api/v1/performances/{performance}', operationId: 'performanceUpdatePatch', security: [['sanctum' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    #[OA\Delete(path: '/api/v1/performances/{performance}', operationId: 'performanceDestroy', security: [['sanctum' => []]], tags: ['Performance'], parameters: [new OA\Parameter(name: 'performance', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 302, description: 'Redirect to the enterprise performance UI')])]
    public function performanceMember(): void {}

    #[OA\Get(path: '/api/v1/recruitments', operationId: 'recruitmentIndex', security: [['sanctum' => []]], tags: ['Recruitment'], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Post(path: '/api/v1/recruitments', operationId: 'recruitmentStore', security: [['sanctum' => []]], tags: ['Recruitment'], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    public function recruitmentCollection(): void {}

    #[OA\Get(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentShow', security: [['sanctum' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Put(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentUpdatePut', security: [['sanctum' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Patch(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentUpdatePatch', security: [['sanctum' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    #[OA\Delete(path: '/api/v1/recruitments/{recruitment}', operationId: 'recruitmentDestroy', security: [['sanctum' => []]], tags: ['Recruitment'], parameters: [new OA\Parameter(name: 'recruitment', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 410, description: 'Use the CQRS recruitment routes')])]
    public function recruitmentMember(): void {}
}
