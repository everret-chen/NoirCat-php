# MySQL 8 verification report

| field | value |
|---|---|
| run | [36085933022](https://github.com/everret-chen/NoirCat-php/actions/runs/36085933022) |
| branch | `ci/mysql-report` |
| commit | `4a301f56ea78ddee6394a59a4e16f042f1d1b56a` |
| date (UTC) | 2026-09-25T02:22:46Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test exit | 1 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 11.01ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 34.46ms DONE
  0001_01_01_000001_create_cache_table ........................... 9.84ms DONE
  0001_01_01_000002_create_jobs_table ........................... 29.18ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 40.53ms DONE
  2026_09_19_234939_create_permission_tables .................... 90.62ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 22.47ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 70.89ms DONE
  2026_09_20_100000_create_categories_table ..................... 15.89ms DONE
  2026_09_20_100100_create_posts_table .......................... 56.75ms DONE
  2026_09_20_100200_create_comments_table ....................... 67.86ms DONE
  2026_09_20_100300_create_likes_table .......................... 29.15ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 38.31ms DONE
  2026_09_21_100100_create_reports_table ........................ 53.13ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 41.11ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 171 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 17 ms DONE  

```

## failure list

```text
   FAILED  Tests\Feature\Web\ModerationPagesTest > the trash restores a dele…   
  Tests:    1 failed, 178 passed (705 assertions)
```

## first failure in full

```text

   PASS  Tests\Unit\MailLogReaderTest
  ✓ it reads the newest link first                                       0.07s  
  ✓ it reports a link once even when the html part repeats it            0.01s  
  ✓ it keeps literal equals signs intact                                 0.01s  
  ✓ it handles a quoted printable body                                   0.01s  
  ✓ it ignores wrapped punctuation and unrelated urls                    0.01s  
  ✓ it returns nothing for an unrelated log                              0.01s  

   PASS  Tests\Unit\MarkdownServiceTest
  ✓ it renders common markdown                                           0.05s  
  ✓ raw html is stripped                                                 0.01s  
  ✓ unsafe link schemes are removed                                      0.01s  
  ✓ images cannot carry event handlers                                   0.01s  
  ✓ style and iframe payloads are dropped                                0.01s  
  ✓ excerpt returns plain text                                           0.01s  

   PASS  Tests\Feature\Api\AuthTest
  ✓ registration cannot assign a role                                    0.89s  
  ✓ profile update cannot target another user                            0.11s  
  ✓ registration creates a user with the default role                    0.11s  
  ✓ registration writes an audit entry without the password              0.11s  
  ✓ registration rejects a weak password                                 0.09s  
  ✓ registration rejects a malformed username                            0.09s  
  ✓ registration rejects a duplicate username                            0.09s  
  ✓ registration rejects a duplicate email                               0.09s  
  ✓ login accepts username or email                                      0.10s  
  ✓ login with a wrong password fails generically                        0.09s  
  ✓ login does not reveal whether the account exists                     0.35s  
  ✓ login is rate limited per ip                                         0.10s  
  ✓ me requires a valid token                                            0.09s  
  ✓ me returns the authenticated user with roles and permissions         0.09s  
  ✓ logout revokes the current token                                     0.09s  
  ✓ profile email can be updated                                         0.09s  
  ✓ profile rejects an email already taken                               0.09s  
  ✓ changing the password requires the current one                       0.10s  
  ✓ changing the password revokes the other tokens                       0.10s  
  ✓ avatar upload accepts images and rejects other files                 0.10s  

   PASS  Tests\Feature\Api\AuthorizationPlumbingTest
  ✓ a member without the permission is forbidden                         0.11s  
  ✓ an admin reaches the permission protected route                      0.10s  
  ✓ a guest is rejected before the permission check                      0.08s  
  ✓ an unverified account is blocked with code 1005                      0.09s  
  ✓ a verified account passes the verified middleware                    0.09s  

   PASS  Tests\Feature\Api\EmailVerificationTest
  ✓ registration sends a verification email                              0.10s  
  ✓ a signed link verifies the email address                             0.09s  
  ✓ a link whose hash does not match the address is rejected             0.09s  
  ✓ unsigned and expired links are rejected                              0.09s  
  ✓ the verification email can be resent                                 0.09s  
  ✓ an already verified address is not mailed again                      0.09s  
  ✓ the resend endpoint requires authentication                          0.08s  

   PASS  Tests\Feature\Api\ErrorEnvelopeTest
  ✓ unknown api route returns route not found                            0.01s  
  ✓ wrong http method returns method not allowed                         0.01s  
  ✓ validation failure returns field errors                              0.01s  
  ✓ business exception keeps its code and message                        0.01s  
  ✓ internal errors are reported with the system code                    0.01s  
  ✓ internal error details are hidden when debug is off                  0.01s  
  ✓ web requests keep the default error page                             0.01s  

   PASS  Tests\Feature\Api\Forum\CommentTest
  ✓ a member can comment and the counter is maintained                   0.11s  
  ✓ guests cannot comment                                                0.09s  
  ✓ a reply to a comment of another post is rejected                     0.10s  
  ✓ nesting beyond the depth limit is rejected                           0.12s  
  ✓ only the author or a moderator can delete a comment                  0.11s  
```

## tail of the run

```text
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
  Duration: 16.27s

```
