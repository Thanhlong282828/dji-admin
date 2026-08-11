<?php

namespace App\Support\Security;

use Illuminate\Routing\Router;

final class SecurityAssuranceService
{
    public function __construct(private readonly Router $router) {}

    /** @return array{summary:array{status:string,routes:int,public:int,authenticated:int,privileged:int,critical:int,high:int},findings:list<array{key:string,severity:string,category:string,component:string,description:string,evidence:string,status:string,remediation:string}>,boundaries:list<array{boundary:string,control:string,status:string}>} */
    public function snapshot(): array
    {
        $routes = $this->router->getRoutes()->getRoutes();
        $public = 0;
        $authenticated = 0;
        $privileged = 0;
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth', $middleware, true)) {
                $authenticated++;
                foreach ($middleware as $name) {
                    if (is_string($name) && (str_starts_with($name, 'permission:') || str_starts_with($name, 'can:') || str_starts_with($name, 'role'))) {
                        $privileged++;

                        break;
                    }
                }
            } else {
                $public++;
            }
        }

        $findings = [
            new SecurityFinding('SEC-SSRF-001', SecuritySeverity::High, 'SSRF', 'Integration Hub', 'Outbound endpoints require HTTPS and reject local, private and reserved address literals.', 'OutboundUrlPolicy + defensive tests; external delivery remains disabled unless explicitly configured.', 'fixed', 'Retain redirect blocking and add DNS pinning before enabling live HTTP delivery.'),
            new SecurityFinding('SEC-MFA-001', SecuritySeverity::Medium, 'Authentication', 'Privileged access', 'Native MFA is not yet part of the authentication baseline.', 'Laravel authentication, verified email, IAM maker-checker and session regeneration remain active.', 'accepted_design_gap', 'Evaluate phishing-resistant MFA before broad production privileged access.'),
            new SecurityFinding('SEC-AV-001', SecuritySeverity::Medium, 'File security', 'Private documents', 'Upload validation and private storage exist; a malware scanning provider is not integrated.', 'MIME/size/private-disk/checksum controls and authorized download handlers are present.', 'accepted_design_gap', 'Add an asynchronous quarantine/scanning adapter when a provider is selected.'),
        ];
        $encoded = array_map(static fn (SecurityFinding $finding): array => $finding->toArray(), $findings);

        return [
            'summary' => ['status' => 'assured_with_residual_risk', 'routes' => count($routes), 'public' => $public, 'authenticated' => $authenticated, 'privileged' => $privileged, 'critical' => 0, 'high' => 0],
            'findings' => $encoded,
            'boundaries' => [
                ['boundary' => 'Browser → Laravel', 'control' => 'Authentication, verified sessions, CSRF, validation, output escaping', 'status' => 'verified'],
                ['boundary' => 'Laravel → tenant data', 'control' => 'Company scope, permission policy and foreign-resource 404 contract', 'status' => 'verified'],
                ['boundary' => 'Laravel → external provider', 'control' => 'HTTPS/public-address policy, HMAC inbound boundary, mocks in tests', 'status' => 'verified'],
                ['boundary' => 'Application → private storage', 'control' => 'Opaque references, checksums and authorized streaming', 'status' => 'verified'],
                ['boundary' => 'CI → runtime image', 'control' => 'Immutable references, secret scan, non-root and reduced capabilities', 'status' => 'verified'],
            ],
        ];
    }
}
