# MySQL 8 verification report

| field | value |
|---|---|
| run | [36086627474](https://github.com/everret-chen/NoirCat-php/actions/runs/36086627474) |
| branch | `dev` |
| commit | `d0287b50c393435b11220b2d3f238c61cfbad6c0` |
| date (UTC) | 2026-09-25T02:33:24Z |
| php | 8.3.35 |
| pdo drivers | dblib,firebird,mysql,odbc,pgsql,sqlite,sqlsrv |
| server version | 8.0.46 |
| tables in noircat_test | 21 |
| migrate:fresh exit | 0 |
| php artisan test exit | 0 |

## migrate:fresh --seed --force

```text

   INFO  Preparing database.  

  Creating migration table ..................................... 181.51ms DONE

   INFO  Running migrations.  

  0001_01_01_000000_create_users_table .......................... 46.63ms DONE
  0001_01_01_000001_create_cache_table .......................... 15.48ms DONE
  0001_01_01_000002_create_jobs_table .......................... 172.54ms DONE
  2026_09_19_230715_create_audit_logs_table .................... 200.38ms DONE
  2026_09_19_234939_create_permission_tables ................... 329.84ms DONE
  2026_09_19_234939_create_personal_access_tokens_table ........ 115.29ms DONE
  2026_09_19_235900_add_profile_columns_to_users_table ......... 231.34ms DONE
  2026_09_20_100000_create_categories_table ..................... 32.42ms DONE
  2026_09_20_100100_create_posts_table .......................... 71.57ms DONE
  2026_09_20_100200_create_comments_table ...................... 114.08ms DONE
  2026_09_20_100300_create_likes_table .......................... 36.62ms DONE
  2026_09_21_100000_add_moderation_columns_to_posts_table ....... 58.05ms DONE
  2026_09_21_100100_create_reports_table ....................... 105.22ms DONE
  2026_09_21_100200_widen_post_body_columns ..................... 52.03ms DONE


   INFO  Seeding database.  

  Database\Seeders\RolesAndPermissionsSeeder ......................... RUNNING  
  Database\Seeders\RolesAndPermissionsSeeder ..................... 180 ms DONE  

  Database\Seeders\CategorySeeder .................................... RUNNING  
  Database\Seeders\CategorySeeder ................................. 16 ms DONE  

```

## php artisan test

```text
  ✓ a listed session can be revoked                                      0.12s  
  ✓ another users session cannot be revoked                              0.10s  
  ✓ every other session can be revoked at once                           0.03s  
  ✓ session management requires authentication                           0.01s  

   PASS  Tests\Feature\Audit\AuditLogServiceTest
  ✓ records an audit entry for the actor                                 0.01s  
  ✓ redacts sensitive payload keys recursively                           0.01s  
  ✓ stores the morph subject and failure result                          0.01s  
  ✓ keeps the trail when the user is deleted                             0.01s  

   PASS  Tests\Feature\Console\NoircatCommandsTest
  ✓ it creates a verified administrator                                  0.08s  
  ✓ it lowercases the address and sanitises the derived username         0.07s  
  ✓ it can leave the address unverified                                  0.07s  
  ✓ it rejects a duplicate address and a weak password                   0.09s  
  ✓ the mail command prints the newest link                              0.07s  
  ✓ the mail command reports an empty log                                0.24s  

   PASS  Tests\Feature\RouteParameterTest
  ✓ a post id with trailing letters is not a route                       0.02s  
  ✓ a comment id with trailing letters is not a route                    0.05s  
  ✓ a session id with trailing letters is not a route                    0.01s  
  ✓ a signed verification link with a lettered id is not a route         0.02s  
  ✓ the numeric forms still work                                         0.06s  

   PASS  Tests\Feature\Web\ModerationPagesTest
  ✓ a member is denied the report queue but keeps their own trash        0.09s  
  ✓ the report queue renders for a moderator                             0.09s  
  ✓ the queue can be filtered by status                                  0.09s  
  ✓ a moderator closes a report from the page                            0.08s  
  ✓ a member cannot close a report                                       0.07s  
  ✓ a moderator can hide and unhide a comment from the thread            0.14s  
  ✓ a member cannot hide someone elses comment                           0.19s  
  ✓ the post toolbar offers the moderator actions                        0.12s  
  ✓ an ordinary member does not see the toolbar                          0.09s  
  ✓ a moderator features locks moves and pins a post from the page       0.17s  
  ✓ a member cannot use the moderator actions                            0.17s  
  ✓ a locked thread hides the comment form                               0.09s  
  ✓ a member reports a post from the page                                0.13s  
  ✓ a member reports a comment from the page                             0.11s  
  ✓ a member cannot report their own post                                0.12s  
  ✓ the page marks content the member already reported                   0.15s  
  ✓ the trash lists and restores own content                             0.12s  
  ✓ the trash restores a deleted comment and its counter                 0.34s  
  ✓ the trash cannot be used to restore someone elses content            0.16s  
  ✓ a business conflict is answered without polluting the error log      0.09s  
  ✓ the navigation only shows governance links to moderators             0.12s  

   PASS  Tests\Feature\Web\VerifiedGateTest
  ✓ the gate is on by default                                            0.07s  
  ✓ it can be switched off locally                                       0.25s  
  ✓ the api answers with the envelope while the gate is off              0.08s  
  ✓ production ignores the switch                                        0.13s  

   PASS  Tests\Feature\Web\WebPagesTest
  ✓ the public pages render                                              0.24s  
  ✓ missing pages answer 404                                             0.14s  
  ✓ guests are redirected to the sign in page                            0.07s  
  ✓ a guest cannot reach the write endpoints                             0.15s  
  ✓ registration signs the new member in                                 0.09s  
  ✓ the sign in form accepts valid credentials                           0.07s  
  ✓ the sign in form rejects a bad password                              0.07s  
  ✓ a signed link verifies the address and redirects to a page           0.07s  
  ✓ an unsigned verification link is rejected                            0.07s  
  ✓ an unverified member cannot write                                    0.08s  
  ✓ a verified member can publish and read back a post                   0.10s  
  ✓ the author can update and delete a post                              0.08s  
  ✓ a member cannot edit someone elses post                              0.08s  
  ✓ a comment is stored as plain text                                    0.09s  
  ✓ a comment cannot be attached to a draft                              0.08s  
  ✓ liking toggles from the post page                                    0.08s  
  ✓ the account pages render for the owner                               0.08s  
  ✓ the profile can be updated from the page                             0.07s  
  ✓ logging out ends the session                                         0.07s  
  ✓ the locale can be switched from the query string                     0.07s  

  Tests:    179 passed (710 assertions)
  Duration: 18.75s

```
