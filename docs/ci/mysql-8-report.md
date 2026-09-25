# MySQL 8 verification report

| field | value |
|---|---|
| run | [36085657495](https://github.com/everret-chen/NoirCat-php/actions/runs/36085657495) |
| branch | `ci/mysql-report` |
| commit | `e2d3daf47561e5af190f88b65593aa44f796f5ec` |
| date (UTC) | 2026-09-25T02:18:36Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test exit | 1 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 17.66ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 39.05ms DONE
  0001_01_01_000001_create_cache_table .......................... 13.12ms DONE
  0001_01_01_000002_create_jobs_table ........................... 30.79ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 41.82ms DONE
  2026_09_19_234939_create_permission_tables ................... 103.20ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 22.62ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 73.06ms DONE
  2026_09_20_100000_create_categories_table ..................... 18.80ms DONE
  2026_09_20_100100_create_posts_table .......................... 66.23ms DONE
  2026_09_20_100200_create_comments_table ....................... 75.12ms DONE
  2026_09_20_100300_create_likes_table .......................... 33.30ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 40.74ms DONE
  2026_09_21_100100_create_reports_table ........................ 61.61ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 48.65ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 227 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 18 ms DONE  

```

## php artisan test

```text
#20 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Routing\Router->Illuminate\Routing\{closure}()
#21 /home/runner/work/NoirCat-php/NoirCat-php/app/Http/Middleware/SetLocale.php(23): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#22 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): App\Http\Middleware\SetLocale->handle()
#23 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Routing/Middleware/SubstituteBindings.php(50): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#24 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Routing\Middleware\SubstituteBindings->handle()
#25 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php(63): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#26 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Auth\Middleware\Authenticate->handle()
#27 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/VerifyCsrfToken.php(87): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#28 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\VerifyCsrfToken->handle()
#29 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/View/Middleware/ShareErrorsFromSession.php(48): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#30 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\View\Middleware\ShareErrorsFromSession->handle()
#31 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(120): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#32 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php(63): Illuminate\Session\Middleware\StartSession->handleStatefulRequest()
#33 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Session\Middleware\StartSession->handle()
#34 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/AddQueuedCookiesToResponse.php(36): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#35 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse->handle()
#36 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Cookie/Middleware/EncryptCookies.php(74): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#37 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Cookie\Middleware\EncryptCookies->handle()
#38 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#39 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Routing/Router.php(821): Illuminate\Pipeline\Pipeline->then()
#40 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Routing/Router.php(800): Illuminate\Routing\Router->runRouteWithinStack()
#41 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Routing/Router.php(764): Illuminate\Routing\Router->runRoute()
#42 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Routing/Router.php(753): Illuminate\Routing\Router->dispatchToRoute()
#43 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(200): Illuminate\Routing\Router->dispatch()
#44 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(180): Illuminate\Foundation\Http\Kernel->Illuminate\Foundation\Http\{closure}()
#45 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#46 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/ConvertEmptyStringsToNull.php(31): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#47 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull->handle()
#48 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TransformsRequest.php(21): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#49 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/TrimStrings.php(51): Illuminate\Foundation\Http\Middleware\TransformsRequest->handle()
#50 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\TrimStrings->handle()
#51 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePostSize.php(27): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#52 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePostSize->handle()
#53 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/PreventRequestsDuringMaintenance.php(109): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#54 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance->handle()
#55 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Http/Middleware/HandleCors.php(61): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#56 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\HandleCors->handle()
#57 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Http/Middleware/TrustProxies.php(58): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#58 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\TrustProxies->handle()
#59 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Middleware/InvokeDeferredCallbacks.php(22): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#60 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks->handle()
#61 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Http/Middleware/ValidatePathEncoding.php(26): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#62 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(219): Illuminate\Http\Middleware\ValidatePathEncoding->handle()
#63 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(137): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}()
#64 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(175): Illuminate\Pipeline\Pipeline->then()
#65 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(144): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter()
#66 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(607): Illuminate\Foundation\Http\Kernel->handle()
#67 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Foundation/Testing/Concerns/MakesHttpRequests.php(368): Illuminate\Foundation\Testing\TestCase->call()
#68 /home/runner/work/NoirCat-php/NoirCat-php/tests/Feature/Web/ModerationPagesTest.php(375): Illuminate\Foundation\Testing\TestCase->get()
#69 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestCase.php(1667): Tests\Feature\Web\ModerationPagesTest->the_trash_restores_a_deleted_comment_and_its_counter()
#70 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestCase.php(519): PHPUnit\Framework\TestCase->runTest()
#71 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestRunner/TestRunner.php(87): PHPUnit\Framework\TestCase->runBare()
#72 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestCase.php(365): PHPUnit\Framework\TestRunner->run()
#73 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestSuite.php(369): PHPUnit\Framework\TestCase->run()
#74 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestSuite.php(369): PHPUnit\Framework\TestSuite->run()
#75 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/Framework/TestSuite.php(369): PHPUnit\Framework\TestSuite->run()
#76 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/TextUI/TestRunner.php(64): PHPUnit\Framework\TestSuite->run()
#77 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/src/TextUI/Application.php(211): PHPUnit\TextUI\TestRunner->run()
#78 /home/runner/work/NoirCat-php/NoirCat-php/vendor/phpunit/phpunit/phpunit(104): PHPUnit\TextUI\Application->run()
#79 {main}

----------------------------------------------------------------------------------

SQLSTATE[42S22]: Column not found: 1054 Unknown column 'slug' in 'field list' (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: noircat_test, SQL: select `id`, `title`, `slug` from `posts` where `posts`.`id` in (78) and `posts`.`deleted_at` is null)

  at tests/Feature/Web/ModerationPagesTest.php:376
    372▕         $this->assertSame(0, $post->refresh()->comment_count);
    373▕ 
    374▕         $this->actingAs($moderator)
    375▕             ->get('/moderation/trash')
  ➜ 376▕             ->assertOk()
    377▕             ->assertSee('被删的评论')
    378▕             ->assertSee(__('moderation_ui.trash.subtitle_all'));
    379▕ 
    380▕         $this->actingAs($moderator)


  Tests:    1 failed, 178 passed (705 assertions)
  Duration: 17.83s

```
