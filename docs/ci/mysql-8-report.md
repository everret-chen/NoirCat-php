# MySQL 8 verification report

| field | value |
|---|---|
| run | [36086647774](https://github.com/everret-chen/NoirCat-php/actions/runs/36086647774) |
| branch | `ci/mysql-report` |
| commit | `571778577310fe49bde1137afb66c0b366ce0d07` |
| date (UTC) | 2026-09-25T02:33:11Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test exit | 0 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ...................................... 72.29ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 42.18ms DONE
  0001_01_01_000001_create_cache_table .......................... 14.01ms DONE
  0001_01_01_000002_create_jobs_table ........................... 34.17ms DONE
  2026_09_19_230715_create_audit_logs_table ..................... 47.45ms DONE
  2026_09_19_234939_create_permission_tables ................... 146.40ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ......... 26.42ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table .......... 81.69ms DONE
  2026_09_20_100000_create_categories_table ..................... 41.08ms DONE
  2026_09_20_100100_create_posts_table .......................... 72.59ms DONE
  2026_09_20_100200_create_comments_table ....................... 81.08ms DONE
  2026_09_20_100300_create_likes_table .......................... 66.10ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 47.31ms DONE
  2026_09_21_100100_create_reports_table ........................ 68.24ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 50.70ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 195 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 15 ms DONE  

```

## failure list

```text
  Tests:    179 passed (710 assertions)
```

## failure detail

```text
```

## first failure in full

```text

   PASS  Tests\Unit\MailLogReaderTest
  ✓ it reads the newest link first                                       0.07s  
  ✓ it reports a link once even when the html part repeats it            0.01s  
  ✓ it keeps literal equals signs intact                                 0.01s  
  ✓ it handles a quoted printable body
  ✓ it ignores wrapped punctuation and unrelated urls
  ✓ it returns nothing for an unrelated log

   PASS  Tests\Unit\MarkdownServiceTest
  ✓ it renders common markdown                                           0.04s  
  ✓ raw html is stripped                                                 0.01s  
  ✓ unsafe link schemes are removed                                      0.01s  
  ✓ images cannot carry event handlers                                   0.01s  
  ✓ style and iframe payloads are dropped                                0.01s  
  ✓ excerpt returns plain text                                           0.01s  

   PASS  Tests\Feature\Api\AuthTest
  ✓ registration cannot assign a role                                    2.53s  
  ✓ profile update cannot target another user                            0.09s  
  ✓ registration creates a user with the default role                    0.10s  
  ✓ registration writes an audit entry without the password              0.10s  
  ✓ registration rejects a weak password                                 0.07s  
  ✓ registration rejects a malformed username                            0.09s  
  ✓ registration rejects a duplicate username                            0.08s  
  ✓ registration rejects a duplicate email                               0.08s  
  ✓ login accepts username or email                                      0.08s  
  ✓ login with a wrong password fails generically                        0.08s  
  ✓ login does not reveal whether the account exists                     0.30s  
  ✓ login is rate limited per ip                                         0.09s  
  ✓ me requires a valid token                                            0.08s  
  ✓ me returns the authenticated user with roles and permissions         0.08s  
  ✓ logout revokes the current token                                     0.09s  
  ✓ profile email can be updated                                         0.08s  
  ✓ profile rejects an email already taken                               0.08s  
  ✓ changing the password requires the current one                       0.09s  
  ✓ changing the password revokes the other tokens                       0.09s  
  ✓ avatar upload accepts images and rejects other files                 0.10s  

   PASS  Tests\Feature\Api\AuthorizationPlumbingTest
```

## tail of the run

```text
  ✓ a member reports a comment from the page                             0.09s  
  ✓ a member cannot report their own post                                0.08s  
  ✓ the page marks content the member already reported                   0.11s  
  ✓ the trash lists and restores own content                             0.09s  
  ✓ the trash restores a deleted comment and its counter                 0.10s  
  ✓ the trash cannot be used to restore someone elses content            0.09s  
  ✓ a business conflict is answered without polluting the error log      0.09s  
  ✓ the navigation only shows governance links to moderators             0.10s  

   PASS  Tests\Feature\Web\VerifiedGateTest
  ✓ the gate is on by default                                            0.08s  
  ✓ it can be switched off locally                                       0.09s  
  ✓ the api answers with the envelope while the gate is off              0.08s  
  ✓ production ignores the switch                                        0.09s  

   PASS  Tests\Feature\Web\WebPagesTest
  ✓ the public pages render                                              0.09s  
  ✓ missing pages answer 404                                             0.07s  
  ✓ guests are redirected to the sign in page                            0.07s  
  ✓ a guest cannot reach the write endpoints                             0.08s  
  ✓ registration signs the new member in                                 0.09s  
  ✓ the sign in form accepts valid credentials                           0.08s  
  ✓ the sign in form rejects a bad password                              0.07s  
  ✓ a signed link verifies the address and redirects to a page           0.08s  
  ✓ an unsigned verification link is rejected                            0.08s  
  ✓ an unverified member cannot write                                    0.08s  
  ✓ a verified member can publish and read back a post                   0.10s  
  ✓ the author can update and delete a post                              0.11s  
  ✓ a member cannot edit someone elses post                              0.09s  
  ✓ a comment is stored as plain text                                    0.10s  
  ✓ a comment cannot be attached to a draft                              0.08s  
  ✓ liking toggles from the post page                                    0.09s  
  ✓ the account pages render for the owner                               0.09s  
  ✓ the profile can be updated from the page                             0.08s  
  ✓ logging out ends the session                                         0.08s  
  ✓ the locale can be switched from the query string                     0.08s  

  Tests:    179 passed (710 assertions)
  Duration: 15.89s

```
