# MySQL 8 verification report

| field | value |
|---|---|
| run | [36086215549](https://github.com/everret-chen/NoirCat-php/actions/runs/36086215549) |
| branch | `ci/mysql-report` |
| commit | `37a3ba955e9772a9acbe9aaf82c9e26e220541ea` |
| date (UTC) | 2026-09-25T02:26:52Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test exit | 1 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 18.31ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 37.75ms DONE
  0001_01_01_000001_create_cache_table .......................... 10.97ms DONE
  0001_01_01_000002_create_jobs_table ........................... 30.22ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 54.21ms DONE
  2026_09_19_234939_create_permission_tables ................... 118.40ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 23.61ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 78.04ms DONE
  2026_09_20_100000_create_categories_table ..................... 17.92ms DONE
  2026_09_20_100100_create_posts_table .......................... 61.75ms DONE
  2026_09_20_100200_create_comments_table ....................... 75.09ms DONE
  2026_09_20_100300_create_likes_table .......................... 32.38ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 42.33ms DONE
  2026_09_21_100100_create_reports_table ........................ 62.72ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 49.56ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 192 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 17 ms DONE  

```

## failure list

```text
   FAILED  Tests\Feature\Web\ModerationPagesTest > the trash restores a dele…   
  Tests:    1 failed, 178 passed (705 assertions)
```

## failure detail

```text
   FAILED  Tests\Feature\Web\ModerationPagesTest > the trash restores a dele…   
  Expected response status code [200] but received 500.
Failed asserting that 500 is identical to 200.

The following exception occurred during the last request:

PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'slug' in 'field list' in /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Connection.php:420
Stack trace:
#0 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Connection.php(420): PDO->prepare()
#1 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Connection.php(827): Illuminate\Database\Connection->Illuminate\Database\{closure}()
#2 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Connection.php(794): Illuminate\Database\Connection->runQueryCallback()
#3 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Connection.php(411): Illuminate\Database\Connection->run()
#4 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3505): Illuminate\Database\Connection->select()
#5 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3490): Illuminate\Database\Query\Builder->runSelect()
#6 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(4080): Illuminate\Database\Query\Builder->Illuminate\Database\Query\{closure}()
#7 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(3489): Illuminate\Database\Query\Builder->onceWithColumns()
#8 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(902): Illuminate\Database\Query\Builder->get()
#9 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(884): Illuminate\Database\Eloquent\Builder->getModels()
#10 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php(212): Illuminate\Database\Eloquent\Builder->get()
#11 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/Relation.php(175): Illuminate\Database\Eloquent\Relations\Relation->get()
#12 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(950): Illuminate\Database\Eloquent\Relations\Relation->getEager()
#13 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(919): Illuminate\Database\Eloquent\Builder->eagerLoadRelation()
#14 /home/runner/work/NoirCat-php/NoirCat-php/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(885): Illuminate\Database\Eloquent\Builder->eagerLoadRelations()
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
  ✓ registration cannot assign a role                                    1.10s  
  ✓ profile update cannot target another user                            0.11s  
  ✓ registration creates a user with the default role                    0.12s  
  ✓ registration writes an audit entry without the password              0.12s  
  ✓ registration rejects a weak password                                 0.10s  
  ✓ registration rejects a malformed username                            0.09s  
  ✓ registration rejects a duplicate username                            0.09s  
  ✓ registration rejects a duplicate email                               0.09s  
  ✓ login accepts username or email                                      0.10s  
  ✓ login with a wrong password fails generically                        0.10s  
  ✓ login does not reveal whether the account exists                     0.32s  
  ✓ login is rate limited per ip                                         0.11s  
  ✓ me requires a valid token                                            0.10s  
  ✓ me returns the authenticated user with roles and permissions         0.10s  
  ✓ logout revokes the current token                                     0.10s  
  ✓ profile email can be updated                                         0.10s  
  ✓ profile rejects an email already taken                               0.10s  
  ✓ changing the password requires the current one                       0.11s  
  ✓ changing the password revokes the other tokens                       0.11s  
  ✓ avatar upload accepts images and rejects other files                 0.11s  

   PASS  Tests\Feature\Api\AuthorizationPlumbingTest
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
  Duration: 17.46s

```
