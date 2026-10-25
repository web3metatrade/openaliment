# Development and verification

The application uses PHP MVC, PDO/MySQL and plain browser modules. Hosting requires PHP 8.4+ and the extensions documented in [cPanel deployment](../DEPLOY_CPANEL.md). Node, Python, npm and Composer are development tools and are not required on hosting.

## Local tools

Start a contribution from the default `dev` branch and open the PR against `dev`.
`main` accepts promotion PRs from this repository's `dev`, or explicit `hotfix/...`
branches. The required `branch-flow` check rejects normal contributions aimed at
`main`. Merge permanent branches using merge commits, then synchronize `main`
back into `dev`; squash is appropriate for short-lived feature branches only.
CI produces commit-bound cPanel artifacts for both branches and never deploys
them. See [the collaboration and environment guide](../operations/COLLABORATION.md).

Use PHP 8.4+ with `pdo_mysql`, `mbstring`, `fileinfo` and `openssl`, Node 24+ and Python 3.12+. Install Node dependencies with `npm ci --ignore-scripts`. Install Python dependencies in a virtual environment:

```sh
python -m venv .venv
```

Activate `.venv` using your platform's standard command, then run:

```sh
python -m pip install -r requirements-dev.txt
python tools/qa/run.py --suite quick
```

The quick suite checks syntax, namespace paths, dependency/original hashes, generated contracts and pure client/bootstrap tests. It does not connect to a database, SMTP, AI or production. `--php /path/to/php` and `--node /path/to/node` are available when these executables are not on PATH. `OA_PHP` and `OA_NODE` are equivalent environment overrides.

`composer.json` declares PHP requirements and the `App\` → `app/` mapping. It introduces no downloaded runtime package. The application keeps its own small autoloader and bundled PHPMailer. A Composer lockfile will be needed if downloaded Composer dependencies are introduced; none are currently installed this way.

## Disposable database

The historical OpenBread archive and administrative screenshots are not distributed in the source repository. Contract generation is self-contained. Before database tests, the runner creates a small **synthetic** legacy import package under `tests/runtime/legacy-handover/`; this exercises the old schema, private import, idempotency, quarantine marker and export without disclosing the original recipe or documents. If a separately authorized original archive exists at the configured `handover_path`, the tests use it and verify its original byte manifest. Synthetic checks do not establish the integrity of an absent original archive.

The `app` packaging profile works from a clean clone. The `full` and `pilot` profiles require the separately supplied historical archive and fail explicitly if it is missing. Never copy original producer materials into a public PR to make these profiles pass.

Create a dedicated **openaliment_tests** database with `utf8mb4`, and a user restricted to that database. The local suite resets all tables there. Never give it a production account.

Configure these environment variables for your shell or CI:

| Variable | Default |
| --- | --- |
| `OA_TEST_DB_HOST` | `127.0.0.1` |
| `OA_TEST_DB_PORT` | `3306` |
| `OA_TEST_DB_USER` | `openaliment_tests` |
| `OA_TEST_DB_PASSWORD` | empty; supply the disposable user's password locally |

Alternatively, copy `tests/config.example.php` to ignored `tests/config.local.php`. `OA_TEST_CONFIG` can explicitly select another private test configuration. All paths use `tests/bootstrap.php`, which checks the DSN and the actual database name before any reset, forces private test storage and replaces email delivery with a private file transport. It never loads `config/local.php` to obtain test credentials.

```sh
python tools/qa/run.py --suite local --reset-test-db
```

The runner creates synthetic photos, runs dependent PHP suites in order, starts a loopback PHP server on an available port, sets `OA_TEST_BASE_URL`, runs HTTP/QR/maintenance checks, and stops its own server. It does not reuse or stop another running preview. The database suites are deliberately sequential.

Fresh results are in `tests/runtime/reports/`; raw logs and private fixtures stay in ignored `tests/runtime/`. Existing evidence in `docs/verification/` is historical and is not overwritten by ordinary test runs. Do not attach raw logs or household/token fixtures to public issues.

## Browser verification

Install the development Chromium build once:

```sh
npx playwright install chromium
python tools/qa/run.py --suite browser --reset-test-db
```

The browser suite includes the local suite. `OA_BROWSER_CHANNEL=msedge` can select an installed Edge build. It is optional; Chromium is the default. Screenshots and exports remain private under `tests/runtime/`. The browser option can also be selected manually in GitHub Actions. Physical camera, packaging, mobile OS and POS acceptance remain separate.

## Contract generation

```sh
python tools/build/artifacts.py
python tools/build/artifacts.py --check
```

Both commands build in a temporary directory. The first copies changed generated JSON to `contracts/`; `--check` fails on drift without rewriting the project. Implementations are in `tools/build/`. The legacy commands `tools/build-contracts.py`, `tools/build-consumer-contracts.py`, `tools/build-integration-contracts.py` and `tools/build-home-contracts.py` remain compatibility entry points.

Public API guides use `docs/api/DEVELOPER_GUIDE.md`, `docs/api/pages/` and the explicitly selected integration sections in `docs/api/sections/`. Do not embed internal implementation reports, deployment procedures or unfinished plans in these sources. Repository test setup and generator maintenance instructions belong here or in `docs/api/README.md`. Public guides describe the integration contract, supported capabilities, genuine limits and synthetic examples. `tests/test_public_content.py` checks all portal pages, guide links, the combined Markdown download and templates and runs in both quick and local QA.

The logical model is generated from the migrations in `database/migrations/`. It describes source code, not the installation state of a particular hosting account. Migration bytes and historical/dependency files are excluded from Git line normalization so their SHA-256 hashes remain stable across platforms.

## Local preview outside QA

For a normal developer preview, use the existing `tools/dev-router.php` with a private application configuration in `config/local.php` and document root `public/`. For the isolated test server use `tools/dev/test-router.php`; it is never deployed to `public/`. The production bootstrap still reads the original private configuration paths.

## Building a cPanel package

```sh
python tools/package-release.py --profile app
python tools/package-release.py --profile full
python tools/package-release.py --profile pilot
```

`app` contains the runtime without the historical pilot archive. `full` preserves the complete application-plus-pilot distribution and is the compatibility default. `pilot` contains the unchanged historical package and licensing information. `handover_path` keeps the original default; configure it privately when installing a separately located pilot package.

Version metadata is in `release.json`, file selection in `tools/build/release-manifest.json`, and generated packages in ignored `release/`. The builder excludes private configs, tests, runtime data, screenshots, output images and Python caches, checks every archived hash and writes SHA-256 alongside the ZIP. Identical input bytes produce an identical ZIP. Distribution of historical documents/images still requires the separate rights described in [LICENSING.md](../../LICENSING.md).

## Public project identity

`app/Domain/PlatformInformation.php` holds the founder-approved public description of the OpenAliment initiative for the configured canonical origin `https://openaliment.ro`. The privacy page and public policy API share that projection, including its version. The contact still comes from private configuration. Other configured origins retain their own `operator_name`, `privacy_text` and `operator_contact`; a request Host header cannot select another identity. Update the canonical description and policy version when the project's organizational status changes. Producer organizations, including the historical MOPAN context, are separate from the identity of the platform initiative.

## Pinned GS1 reference for offline tests

Prepare the official URI PDF with python tools/standards/fetch.py --only uri-syntax --directory tests/runtime/reference-gs1, then set OA_GS1_REFERENCE_DIR=tests/runtime/reference-gs1. The HTTP suite uses the verified cached PDF without network access. CI prepares this public resource explicitly. See [GS1 references](../standards/GS1.md).
