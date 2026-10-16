![PHP](https://img.shields.io/badge/PHP-%20^8.2-777BB4)
![PHPStan-Level](https://img.shields.io/badge/PHPStan-Level%209-224488)
![Pest-php](https://img.shields.io/badge/Tests-Passed-019733)
![License](https://img.shields.io/badge/License-MIT-777)

# Database

A full implementation of the [stougeiro/database-contract](https://github.com/stougeiro/database-contract) for PHP, shipping a complete SQLite driver with every capability — transactions, batch, upsert, streaming, aggregation and search (FTS5 with a `LIKE` fallback).

Same philosophy as the contract: engine code is isolated behind factories, values are typed, and the query tree never leaks into the driver.

## ✨ Features

- **Complete SQLite driver**  
  CRUD, listing, ordering and pagination over PDO, with every optional capability enabled.

- **Typed, injection-safe values**  
  All values travel as `Param` (or are auto-wrapped from raw scalars at the Model edge), so nothing is ever interpolated into SQL.

- **Neutral query tree**  
  Filters, order and pagination are validated by builders (`FilterBuilder`, `OrderBuilder`, `PaginationBuilder`) and translated into the canonical driver-edge arrays — drivers never see the QueryTree.

- **Optional capabilities, opt-in per driver**  
  `transactions`, `batch`, `upsert`, `streaming`, `aggregation` and `search` are announced through `DriverCapabilitiesInterface` and enforced at the Model edge.

- **Manager orchestration**  
  Named connections resolved lazily through `ConnectionFactory` → `ConnectionRegistry` → `DriverFactory`, with per-connection driver caching and clean `disconnect()` / `purge()`.

- **Active-record-ish Model**  
  `ModelAbstracted` gives a simple array-based CRUD/search/stream/upsert/batch/aggregate surface over any resource, auto-wrapping scalars and failing fast on invalid filters.

- **DI-friendly**  
  Factories are the seam for any container; nothing is hard-wired to a service locator.

- **Lightweight by design**  
  No runtime dependencies beyond the contract, at PHPStan level 9.

---

## 📦 Installation

Install via Composer:

```bash
composer require stougeiro/database
```

Requires PHP ^8.2 and `ext-pdo_sqlite` for the bundled driver.

## 🔧 Drivers

| Alias         | Connection         | Driver       | Capabilities |
|---------------|--------------------|--------------|--------------|
| `pdo-sqlite`  | `SqliteConnection` | `SqliteDriver` | transactions, batch, upsert, streaming, aggregation, search (FTS5 / LIKE) |

Additional aliases can be registered on the `DriverFactory` (callable receives the connection and its config).

### Search on SQLite

`search()` uses FTS5 `MATCH` when the resource is an FTS5 virtual table (detected via `sqlite_master`); otherwise it falls back to a case-insensitive `LIKE` across the resource columns — from the attached metadata (`fields()`) when available, or `PRAGMA table_info` when not.

### Cursor pagination

`list()`, `search()` and `stream()` accept a `cursor` in the pagination array; on SQLite it maps to a keyset over `rowid` (the result set is then ordered by `rowid ASC`).

---

## 🚀 Usage Example

```php
use STDW\Database\Manager\ConnectionFactory;
use STDW\Database\Manager\ConnectionRegistry;
use STDW\Database\Manager\DatabaseManager;
use STDW\Database\Manager\DriverFactory;

$manager = new DatabaseManager(
    connections: [
        'default' => ['driver' => 'pdo-sqlite', 'database' => ':memory:'],
    ],
    connectionFactory: new ConnectionFactory(),
    driverFactory: new DriverFactory(),
    registry: new ConnectionRegistry(),
);

$driver = $manager->driver(); // SqliteDriver over the "default" connection
```

### Model

```php
use STDW\Database\Model\ModelAbstracted;

final class User extends ModelAbstracted
{
    protected string $resource = 'users';
    protected string $connection = 'default';
}

$users = new User($manager);

$ada = $users->create(['name' => 'Ada', 'age' => 36]);

$users->list(['age GTE' => 18], ['age' => 'desc'], ['limit' => 10]);
$users->search(['name LIKE' => '%Ada%']);
$users->upsert(['name' => 'Ada', 'age' => 99], ['name' => 'Ada']);
$users->begin();
$users->update(['age' => 37], ['name' => 'Ada']);
$users->commit();
```

Raw scalars are auto-wrapped into typed `Param`s; invalid filters fail fast with `QueryTreeException` before the driver ever runs.

---

## 🧠 Why?

Because the contract defines *what* a driver must do — this package shows *how*, and does it completely for SQLite.

The layering is deliberate: connections own only their life cycle, drivers own execution and capabilities, the manager owns orchestration, and the Model owns developer ergonomics. Each layer can be swapped or stubbed independently, and nothing engine-specific leaks upward.

---

## 🤝 Contributions

Contributions are welcome.
Feel free to open issues or submit pull requests.

<br>

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="170"/>](https://www.buymeacoffee.com/stougeiro)
