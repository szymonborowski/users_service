# Konkretna propozycja testów – serwis Users

Poniżej lista **konkretnych testów** do dodania w repozytorium `users`: nazwy metod, endpointy, payload, oczekiwane asercje. Układ: najpierw **RoleController (Internal API)**, potem **Internal API UserController (updateById, destroyById)**, na końcu **Email (unit)**.

---

## 1. RoleController (Internal API) – nowy plik `tests/Feature/RoleInternalApiTest.php`

Wzorować na `InternalApiTest`: `RefreshDatabase`, mock `RabbitMQService`, `config(['services.internal.api_key' => 'test-api-key'])`, nagłówek `X-Internal-Api-Key: test-api-key`. W `setUp` wywołać seeder ról (np. `RolesSeeder` lub ręcznie `Role::create` dla admin, moderator, author, reader), żeby w bazie były role.

| # | Nazwa metody testu | Request | Oczekiwany wynik |
|---|--------------------|---------|-------------------|
| 1 | `internal_roles_require_api_key` | `GET /api/internal/roles` bez nagłówka | `401 Unauthorized` |
| 2 | `internal_roles_reject_invalid_api_key` | `GET /api/internal/roles` z `X-Internal-Api-Key: invalid` | `401` |
| 3 | `internal_index_returns_roles_ordered_by_level_desc` | `GET /api/internal/roles` z poprawnym kluczem | `200`, JSON tablica ról, pola `id`, `name`, `description`, `level`; kolejność: admin (100), moderator (50), author (20), reader (10) |
| 4 | `internal_get_user_roles_returns_roles_for_user` | Utworzyć użytkownika, przypisać rolę `author`, `GET /api/internal/users/{id}/roles` | `200`, `["author"]` (lub więcej jeśli przypisane) |
| 5 | `internal_get_user_roles_returns_404_for_nonexistent_user` | `GET /api/internal/users/99999/roles` | `404`, `message: User not found` |
| 6 | `internal_assign_role_adds_role_to_user` | Utworzyć użytkownika, `POST /api/internal/users/{id}/roles` body `{"role": "author"}` | `200`, `message: Role assigned`, w `user.roles` jest `author` |
| 7 | `internal_assign_role_returns_404_for_nonexistent_user` | `POST /api/internal/users/99999/roles` body `{"role": "author"}` | `404` |
| 8 | `internal_assign_role_validation_fails_without_role` | `POST /api/internal/users/{id}/roles` body `{}` | `422`, błąd walidacji `role` |
| 9 | `internal_assign_role_validation_fails_for_nonexistent_role` | `POST /api/internal/users/{id}/roles` body `{"role": "nonexistent"}` | `422`, błąd walidacji `role` |
| 10 | `internal_remove_role_removes_role_from_user` | Użytkownik z rolą `reader`, `DELETE /api/internal/users/{id}/roles/reader` | `200`, `message: Role removed`, w `user.roles` brak `reader` |
| 11 | `internal_remove_role_returns_404_for_nonexistent_user` | `DELETE /api/internal/users/99999/roles/reader` | `404` |
| 12 | `internal_remove_role_returns_404_for_nonexistent_role` | `DELETE /api/internal/users/{id}/roles/nonexistent` | `404`, `message: Role not found` |

Uwaga: w testach z przypisywaniem/usuwaniem ról trzeba mieć w bazie role – albo `$this->seed(RolesSeeder::class)`, albo ręcznie `Role::create([...])` dla każdej używanej roli w `setUp`.

---

## 2. UserController Internal API – rozszerzenie `tests/Feature/InternalApiTest.php`

Dodać do istniejącego pliku (ten sam `setUp` z mockiem RabbitMQ i configiem api_key):

| # | Nazwa metody testu | Request | Oczekiwany wynik |
|---|--------------------|---------|-------------------|
| 13 | `update_by_id_updates_user_and_returns_200` | `PUT /api/internal/users/{id}` body `{"name": "Updated Name"}` (użytkownik istnieje) | `200`, JSON z `name: "Updated Name"`, w bazie `users` rekord z tym `name` |
| 14 | `update_by_id_returns_404_for_nonexistent_user` | `PUT /api/internal/users/99999` body `{"name": "X"}` | `404` |
| 15 | `update_by_id_can_update_email_to_unique_value` | Użytkownik A, `PUT /api/internal/users/{id}` body `{"email": "new@example.com"}` | `200`, w bazie `email = new@example.com` |
| 16 | `destroy_by_id_returns_204_and_deletes_user` | Utworzyć użytkownika, `DELETE /api/internal/users/{id}` | `204 No Content`, `assertDatabaseMissing('users', ['id' => $id])` |
| 17 | `destroy_by_id_returns_404_for_nonexistent_user` | `DELETE /api/internal/users/99999` | `404` |

---

## 3. Email (Value Object) – nowy plik `tests/Unit/EmailTest.php`

Klasa `Email` przy nieprawidłowym adresie rzuca `PHPUnit\Framework\AssertionFailedError` (faktycznie używany w kodzie). Testy to odzwierciedlają.

| # | Nazwa metody testu | Kod / wejście | Oczekiwany wynik |
|---|--------------------|----------------|------------------|
| 18 | `accepts_valid_email` | `new Email('user@example.com')` + `getValue()` | Brak wyjątku, `getValue() === 'user@example.com'` |
| 19 | `accepts_valid_email_with_subdomain` | `new Email('user@mail.example.com')` | Brak wyjątku, `getValue()` zwraca ten sam string |
| 20 | `throws_on_invalid_format` | `new Email('not-an-email')` | `AssertionFailedError` (lub `expectException`) |
| 21 | `throws_on_empty_string` | `new Email('')` | Wyjątek (walidacja required) |
| 22 | `throws_on_missing_at` | `new Email('userexample.com')` | Wyjątek |

Uwaga: walidacja `email:rfc,dns` może wymagać rozwiązywania DNS; jeśli w CI bez DNS test z prawdziwą domeną będzie niestabilny, można ograniczyć do `user@example.com` (example.com jest zarezerwowane) i jednego przypadku z nieprawidłowym formatem.

---

## 4. Podsumowanie

| Plik | Nowe / rozszerzenie | Liczba testów |
|------|---------------------|----------------|
| `tests/Feature/RoleInternalApiTest.php` | nowy | 12 |
| `tests/Feature/InternalApiTest.php` | rozszerzenie | 5 |
| `tests/Unit/EmailTest.php` | nowy | 5 |
| **Razem** | | **22** |

Po implementacji:
- **RoleController** przejdzie z 0% na pełne pokrycie ścieżek Internal API (index, getUserRoles, assignRole, removeRole).
- **UserController** (internal) – dopisane updateById i destroyById.
- **Email** – pełne pokrycie wartości i walidacji.

Jeśli chcesz, mogę w kolejnym kroku rozpisać **gotowe ciała metod** (PHP) dla każdego z tych testów (np. w formie plików gotowych do wklejenia).
