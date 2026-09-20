<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_the_success_envelope(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure(['code', 'message', 'data' => ['status', 'locale', 'time']])
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_unsupported_client_languages_fall_back_to_the_site_default(): void
    {
        // "de" is not supported, so the site default (zh_CN) wins.
        $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->getJson('/api/health');

        $response->assertJsonPath('data.locale', 'zh_CN')
            ->assertJsonPath('message', '服务正常')
            ->assertHeader('Content-Language', 'zh_CN');
    }

    public function test_locale_is_resolved_from_the_accept_language_header(): void
    {
        $response = $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->getJson('/api/health');

        $response->assertJsonPath('data.locale', 'en')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_short_accept_language_codes_match_the_supported_locale(): void
    {
        $response = $this->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')->getJson('/api/health');

        $response->assertJsonPath('data.locale', 'zh_CN');
    }

    public function test_locale_can_be_forced_with_the_query_parameter(): void
    {
        // An explicit choice overrides the client language.
        $response = $this->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')->getJson('/api/health?lang=en');

        $response->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('message', 'Service is healthy')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_locale_can_be_forced_with_the_header(): void
    {
        $response = $this->withHeader('X-Locale', 'en')->getJson('/api/health');

        $response->assertJsonPath('data.locale', 'en');
    }

    public function test_unsupported_locale_parameter_is_ignored(): void
    {
        $response = $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')->getJson('/api/health?lang=fr');

        $response->assertJsonPath('data.locale', 'zh_CN');
    }
}
