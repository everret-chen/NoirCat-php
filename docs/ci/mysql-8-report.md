# MySQL 8 verification report

| field | value |
|---|---|
| run | [36088689048](https://github.com/everret-chen/NoirCat-php/actions/runs/36088689048) |
| branch | `ci/mysql-report` |
| commit | `b7a88741cce32518efb0f646cf6e03ee5a53d673` |
| date (UTC) | 2026-09-25T03:03:16Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test (mysql) exit | 0 |
| quality: test exit |  |
| quality: phpstan exit |  |
| quality: composer audit exit |  |
| assets: npm ci exit |  |
| assets: npm run build exit |  |
| mysql cli version exit |  |
| mysql cli query exit |  |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 51.48ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 26.81ms DONE
  0001_01_01_000001_create_cache_table ........................... 8.61ms DONE
  0001_01_01_000002_create_jobs_table ........................... 25.80ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 30.12ms DONE
  2026_09_19_234939_create_permission_tables .................... 81.48ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 15.91ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 53.15ms DONE
  2026_09_20_100000_create_categories_table ..................... 12.91ms DONE
  2026_09_20_100100_create_posts_table .......................... 44.48ms DONE
  2026_09_20_100200_create_comments_table ....................... 51.99ms DONE
  2026_09_20_100300_create_likes_table .......................... 22.78ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 29.70ms DONE
  2026_09_21_100100_create_reports_table ........................ 45.50ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 37.07ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 114 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 10 ms DONE  

```

## php artisan test (against MySQL)

```text
  Tests:    179 passed (710 assertions)
   PASS  Tests\Feature\Web\WebPagesTest
  ✓ the public pages render                                              0.10s  
  ✓ missing pages answer 404                                             0.05s  
  ✓ guests are redirected to the sign in page                            0.05s  
  ✓ a guest cannot reach the write endpoints                             0.05s  
  ✓ registration signs the new member in                                 0.06s  
  ✓ the sign in form accepts valid credentials                           0.05s  
  ✓ the sign in form rejects a bad password                              0.05s  
  ✓ a signed link verifies the address and redirects to a page           0.05s  
  ✓ an unsigned verification link is rejected                            0.05s  
  ✓ an unverified member cannot write                                    0.05s  
  ✓ a verified member can publish and read back a post                   0.06s  
  ✓ the author can update and delete a post                              0.06s  
  ✓ a member cannot edit someone elses post                              0.06s  
  ✓ a comment is stored as plain text                                    0.06s  
  ✓ a comment cannot be attached to a draft                              0.05s  
  ✓ liking toggles from the post page                                    0.06s  
  ✓ the account pages render for the owner                               0.06s  
  ✓ the profile can be updated from the page                             0.05s  
  ✓ logging out ends the session                                         0.05s  
  ✓ the locale can be switched from the query string                     0.06s  

  Tests:    179 passed (710 assertions)
  Duration: 10.40s

```

## php artisan test (sqlite, as the quality job runs it)

```text
  Tests:    179 passed (710 assertions)
```

## phpstan

```text
Note: Using configuration file /home/runner/work/NoirCat-php/NoirCat-php/phpstan.neon.

 [OK] No errors                                                                 

```

## composer audit

```text
No security vulnerability advisories found.
```

## npm ci

```text

added 89 packages in 778ms
```

## npm run build

```text

> build
> vite build

[36mvite v6.4.3 [32mbuilding for production...[36m[39m
transforming...
[32m✓[39m 59 modules transformed.
rendering chunks...
computing gzip size...
[2mpublic/build/[22m[32mmanifest.json            [39m[1m[2m  0.27 kB[22m[1m[22m[2m │ gzip:  0.15 kB[22m
[2mpublic/build/[22m[2massets/[22m[35mapp-Dq6BfqQ_.css  [39m[1m[2m 35.07 kB[22m[1m[22m[2m │ gzip:  6.61 kB[22m
[2mpublic/build/[22m[2massets/[22m[36mapp-vUYjXw4y.js   [39m[1m[2m107.36 kB[22m[1m[22m[2m │ gzip: 38.86 kB[22m
[32m✓ built in 618ms[39m
```

## mysql client probe

```text
mysql  Ver 8.0.46-0ubuntu0.24.04.4 for Linux on x86_64 ((Ubuntu))
mysql: [Warning] Using a password on the command line interface can be insecure.
21
```
