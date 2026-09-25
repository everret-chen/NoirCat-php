# MySQL 8 verification report

| field | value |
|---|---|
| run | [36089033747](https://github.com/everret-chen/NoirCat-php/actions/runs/36089033747) |
| branch | `ci/mysql-report` |
| commit | `35b5b51b8aa1323ca9e9f4b67a2978948678b850` |
| date (UTC) | 2026-09-25T03:08:33Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test (mysql) exit | 0 |
| quality: test exit | 0 |
| quality: phpstan exit | 0 |
| quality: composer audit exit | 0 |
| assets: npm ci exit | 0 |
| assets: npm run build exit | 0 |
| mysql cli version exit | 0 |
| mysql cli query exit | 0 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 18.28ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 37.27ms DONE
  0001_01_01_000001_create_cache_table .......................... 16.01ms DONE
  0001_01_01_000002_create_jobs_table ........................... 35.49ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 49.32ms DONE
  2026_09_19_234939_create_permission_tables ................... 116.10ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 27.77ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 74.11ms DONE
  2026_09_20_100000_create_categories_table ..................... 16.44ms DONE
  2026_09_20_100100_create_posts_table .......................... 65.25ms DONE
  2026_09_20_100200_create_comments_table ....................... 76.48ms DONE
  2026_09_20_100300_create_likes_table .......................... 33.71ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 45.72ms DONE
  2026_09_21_100100_create_reports_table ........................ 60.97ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 53.96ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 192 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 20 ms DONE  

```

## php artisan test (against MySQL)

```text
  Tests:    179 passed (710 assertions)
   PASS  Tests\Feature\Web\WebPagesTest
  ✓ the public pages render                                              0.11s  
  ✓ missing pages answer 404                                             0.10s  
  ✓ guests are redirected to the sign in page                            0.10s  
  ✓ a guest cannot reach the write endpoints                             0.10s  
  ✓ registration signs the new member in                                 0.12s  
  ✓ the sign in form accepts valid credentials                           0.10s  
  ✓ the sign in form rejects a bad password                              0.10s  
  ✓ a signed link verifies the address and redirects to a page           0.10s  
  ✓ an unsigned verification link is rejected                            0.10s  
  ✓ an unverified member cannot write                                    0.10s  
  ✓ a verified member can publish and read back a post                   0.12s  
  ✓ the author can update and delete a post                              0.12s  
  ✓ a member cannot edit someone elses post                              0.11s  
  ✓ a comment is stored as plain text                                    0.13s  
  ✓ a comment cannot be attached to a draft                              0.11s  
  ✓ liking toggles from the post page                                    0.11s  
  ✓ the account pages render for the owner                               0.11s  
  ✓ the profile can be updated from the page                             0.10s  
  ✓ logging out ends the session                                         0.10s  
  ✓ the locale can be switched from the query string                     0.10s  

  Tests:    179 passed (710 assertions)
  Duration: 17.54s

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

added 89 packages in 1s
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
[32m✓ built in 1.05s[39m
```

## mysql client probe

```text
mysql  Ver 8.0.46-0ubuntu0.24.04.4 for Linux on x86_64 ((Ubuntu))
mysql: [Warning] Using a password on the command line interface can be insecure.
21
```
