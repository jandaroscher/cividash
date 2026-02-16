<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\LocaleDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleDetectorTest extends TestCase
{
    protected LocaleDetector $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new LocaleDetector;
    }

    public function test_en_prefix_sets_english_locale(): void
    {
        $request = Request::create('/en/some-page', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('en', App::getLocale());
    }

    public function test_exact_en_path_sets_english_locale(): void
    {
        $request = Request::create('/en', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('en', App::getLocale());
    }

    public function test_default_path_sets_german_locale(): void
    {
        $request = Request::create('/some-page', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_root_path_sets_german_locale(): void
    {
        $request = Request::create('/', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_admin_paths_force_german_locale(): void
    {
        // Set locale to English first to verify middleware overrides it
        App::setLocale('en');

        $request = Request::create('/admin', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_admin_subpath_forces_german_locale(): void
    {
        App::setLocale('en');

        $request = Request::create('/admin/dashboard', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_en_nested_path_sets_english_locale(): void
    {
        $request = Request::create('/en/pages/about', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('en', App::getLocale());
    }

    public function test_path_starting_with_en_but_not_locale_prefix_sets_german(): void
    {
        // "enterprise" starts with "en" but is not the locale prefix "en/"
        $request = Request::create('/enterprise', 'GET');

        $this->middleware->handle($request, fn () => response('ok'));

        $this->assertEquals('de', App::getLocale());
    }

    public function test_middleware_calls_next_handler(): void
    {
        $request = Request::create('/en/test', 'GET');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return response('ok');
        });

        $this->assertTrue($called);
    }
}
