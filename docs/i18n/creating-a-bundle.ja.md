# バンドルを作成する

🌐 [English](../creating-a-bundle.md) · [Français](creating-a-bundle.fr.md) · **日本語**

このドキュメントは、**独立して配布される**Kintaiバンドルを作成する人向けです——独自のGitリポジトリ、独自のバージョン履歴を持ち、Kintai自体のコードベースに一切触れることなく `/admin/bundles/market` からインストールできるものです。従来の、今も使える、バンドルを本体リポジトリの `src/Bundles/` に直接置くパターンはここでは扱いません（それについては `docs/architecture.md` の「Modular Bundles」を参照してください）——このフォルダは手動で置かれたバンドルのために今もスキャンされますが、公式バンドルはすべてここで説明する方式で配布されており、新しいバンドル、特にサードパーティ製のものも同様にすべきです。

`kintai-bundle-notebook`（チームノート：独自のモデル、リポジトリ、マイグレーション、ビュー、ダッシュボードウィジェット、宣言を持つ）は、小さく完結した実例です。読みながら別タブで開いておくと便利です。

## なぜ別リポジトリで、なぜ `git clone` を使わないのか

Kintaiはバンドルの取得に `git clone`/`git pull` を一切使いません。インストーラー（`BundleInstallerService`）は**タグ付きGitHub Releaseのzipball**を通常のHTTPでダウンロードします（curl → PHPストリームへのフォールバック付き、`HttpFetcher` を参照）。これはKintai自身のアップデート（`GithubUpdateService`）とまったく同じ仕組みです。これは意図的なもので、Kintaiが対象とする共有ホスティングの多くはPHPから `git` コマンドを使えることを保証しませんが、外向きのHTTPSリクエストは必ず使えます。

以下のすべてを左右する帰結：
- バンドルには**`vX.Y.Z` タグの付いたGitHub Releaseが少なくとも1つ**必要です。タグだけでは不十分で、`zipball_url` は実際のReleaseにしか存在しません。
- zipballの唯一のルートフォルダが、インストールされたバンドルのルートになります。GitHubが付ける名前（`{owner}-{repo}-{sha}`）は関係なく、中身だけが重要です。
- リリースにビルドやアセットのアップロードは不要です。GitHubがタグ付きコミットから `zipball_url` を自動で生成します。

## 必要な構成

```
your-bundle/
  bundle.json                 # manifest — see below, required
  src/
    YourBundle.php            # entry class, extends kintai\Core\BundleContract\Bundle
    Controllers/Web/...
    Controllers/Api/...
  database/migrations/        # optional — see "Database migrations" below
  Views/                      # optional — loaded via loadViewsFrom()
  public/{css,js}/...         # optional — see "Assets" below, loaded via loadAssetsFrom()
  lang/{en,fr,ja}.json         # optional — bundle-specific translation keys
  routes.php                  # optional — loaded via loadRoutesFrom()
  README.md
  LICENSE
```

間違えやすい点が2つあります：
- **`src/` に置くのはエントリクラス（と同じ名前空間のもの）だけです。** `routes.php`、`Views/`、`lang/` はバンドルのルートに、`src/` と並べて置きます（中には置きません）。これは `bundle.json` の `namespace` フィールドが `src/` だけのPSR-4ルートであることに対応しています。
- `Bundle::getPath()` は、クラスファイルがどちらのフォルダにあっても、バンドルの**ルート**（`bundle.json` を含むフォルダ）を返します。上の構成に従っていれば、`loadRoutesFrom($this->getPath() . '/routes.php')` と `loadViewsFrom($this->getPath() . '/Views', 'your-namespace')` はどちらも正しく解決されます。

## 安定した契約：`kintai\Core\BundleContract\Bundle`

エントリクラスはこの抽象クラスを継承します。これは `kintai\Core\*` の中で唯一、独自のセマンティックバージョニングに従い、変更時にCHANGELOGで告知される部分です（任意のメソッドの追加はマイナー、変更や削除はドキュメント化されたメジャー変更）。`kintai\Core\` のそれ以外のものにはこの保証はありません。

```php
abstract class Bundle
{
    abstract public function getName(): string;      // slug — must match bundle.json's "slug"
    abstract public function register(): void;        // wire services, routes, views

    public function getVersion(): string;              // must match bundle.json's "version"
    public function getLabel(): string;                 // shown in /admin/bundles(/market)
    public function getDescription(): string;
    public function boot(): void;                       // runs once every bundle is registered
    public function getPath(): string;                  // bundle root, see above

    protected function loadRoutesFrom(string $path): void;
    protected function loadViewsFrom(string $path, string $namespace): void;
    protected function loadAssetsFrom(string $relativeDir): void;  // see "Assets" below
}
```

最小限の実例（`kintai-bundle-notebook` の実際のエントリクラスを短くしたもの）：

```php
<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook;

use kintai\Bundles\Installed\Notebook\Repositories\DatabaseNotebookEntryRepository;
use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Contract\Bundle;
use kintai\Contract\Extension\StoreFeature;

final class NotebookBundle extends Bundle
{
    public function getName(): string { return 'notebook'; }
    public function getVersion(): string { return '1.2.0'; }
    public function getLabel(): string { return __('bundle_notebook'); }
    public function getDescription(): string { return __('bundle_notebook_desc'); }

    public function register(): void
    {
        // The bundle owns its repository (and its table, created by its own migrations).
        $this->app->container()->singleton(
            NotebookEntryRepositoryInterface::class,
            fn() => new DatabaseNotebookEntryRepository()
        );
        $this->loadViewsFrom($this->getPath() . '/Views', 'notebook');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    // What it brings to the Core is declared (see "Extension points" below).
    public function permissions(): array { return ['notebook' => ['view', 'create', 'manage']]; }

    public function storeFeatures(): array
    {
        return [new StoreFeature('notes', 'feature_notes', 'feature_notes_desc', '📝', 90)];
    }
}
```

名前空間は自由ですが、慣例としてインストール型バンドルは `kintai\Bundles\Installed\{Name}\` を使います。これは旧モノレポの慣例（`kintai\Bundles\{Name}\`）とあえて区別しており、2つのオートロード機構（`src/Bundles/` 用のComposerのPSR-4と、`storage/bundles/` 以下用の専用 `spl_autoload_register`）が同じクラス名で衝突しないようにするためです。

**バンドルはCoreのリポジトリやモデルに決して依存しません**：自分のものを持ち（上の例のように）、以下に挙げる `kintai\Contract\…` の名前だけを使います。Coreが必要とするデータは、宣言されたプロバイダーを通じて渡します。

## 契約の公開面：`kintai\Contract\…`

バンドルが使えるのは以下の名前（と自分のクラス）だけです。それぞれは現在それを実装しているCoreクラスの**安定したエイリアス**で（参照リストは `src/Contract/surface.php`）、Coreはバンドルを壊さずに自分のコードを再編できます。通常のクラスと同じようにインポートしてください——型宣言、`instanceof`、`catch`、コンストラクタインジェクションはすべて機能し、コンテナはエイリアスをその裏にあるクラスのバインディングに解決します。

```php
use kintai\Contract\Bundle;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreRepositoryInterface;
```

**安定（stable）**の名前は契約のメジャーバージョンの間ずっと保証されます。**暫定（provisional）**の名前は移行のために公開されており、契約計画の後の段階で置き換えられます（各説明を参照）。それらがなくなるのは新しいメジャーバージョンのときだけです。

| 名前 | 状態 | 役割 |
|---|---|---|
| `kintai\Contract\Bundle` | 安定 | すべてのバンドルの基底クラス（エントリポイント）。 |
| `kintai\Contract\BundleManifest` | 安定 | 解析済みの bundle.json。 |
| `kintai\Contract\Container` | 安定 | 依存性コンテナ（独自のバインディングを登録）。 |
| `kintai\Contract\Cron\CronJob` | 安定 | `Bundle::cronJobs()` で宣言する定期ジョブ（契約1.8）。 |
| `kintai\Contract\Http\Request` | 安定 | HTTPリクエスト。 |
| `kintai\Contract\Http\Response` | 安定 | HTTPレスポンス。 |
| `kintai\Contract\Http\Router` | 安定 | ルート宣言（routes.php）。 |
| `kintai\Contract\Http\Paginator` | 安定 | APIの一覧のページネーション。 |
| `kintai\Contract\Http\Middleware\AuthMiddleware` | 安定 | Webセッションが必要。 |
| `kintai\Contract\Http\Middleware\PermissionMiddleware` | 安定 | Webの権限が必要。 |
| `kintai\Contract\Http\Middleware\ApiAuthMiddleware` | 安定 | APIトークンが必要。 |
| `kintai\Contract\Http\Middleware\ApiPermissionMiddleware` | 安定 | APIの権限が必要。 |
| `kintai\Contract\Http\Concerns\HasBaseUrl` | 安定 | URLのプレフィックス（サブフォルダへのインストール）。 |
| `kintai\Contract\Http\Concerns\HasAdminAccess` | **暫定** | 店長の店舗範囲。CurrentUser/Gate に置き換え予定（ステップ2）。 |
| `kintai\Contract\Http\Concerns\HasStoreFeatureCheck` | **暫定** | 店舗ごとの機能確認。storeFeatures 拡張ポイントに置き換え予定（ステップ2）。 |
| `kintai\Contract\Http\Concerns\HasStaffReportCrud` | **暫定** | 人事レポート共通のCRUD。該当バンドルへ移動予定（ステップ4）。 |
| `kintai\Contract\Exceptions\NotFoundException` | 安定 | 404。 |
| `kintai\Contract\Exceptions\ForbiddenException` | 安定 | 403。 |
| `kintai\Contract\Exceptions\ValidationException` | 安定 | フィールドごとのエラー付き422。 |
| `kintai\Contract\Exceptions\ConflictException` | 安定 | 409。 |
| `kintai\Contract\Auth\PermissionService` | 安定 | 権限と範囲の確認。 |
| `kintai\Contract\Auth\AuthService` | **暫定** | セッションのユーザー。CurrentUser に置き換え予定（ステップ2）。 |
| `kintai\Contract\Auth\UserAnonymizer` | 安定 | アカウントの削除：`anonymize($userId)` は個人データを消去し、アカウントを無効化して権限とトークンを取り消します。履歴は「削除されたユーザー」に紐づいたまま残ります（契約1.21。このために `UserRepositoryInterface::delete()` を呼ばないでください）。 |
| `kintai\Contract\Data\UserRepositoryInterface` | 安定 | アカウント。 |
| `kintai\Contract\Data\StoreRepositoryInterface` | 安定 | 店舗。 |
| `kintai\Contract\Data\StoreUserRepositoryInterface` | 安定 | 店舗への所属。 |
| `kintai\Contract\Data\ShiftRepositoryInterface` | 安定 | シフト。 |
| `kintai\Contract\Data\ShiftTypeRepositoryInterface` | 安定 | シフトタイプ。 |
| `kintai\Contract\Data\UserShiftTypeRateRepositoryInterface` | 安定 | 個人ごとの時給。 |
| `kintai\Contract\Data\AppSettingsRepositoryInterface` | 安定 | インスタンスの設定。 |
| `kintai\Contract\Database\Migration` | 安定 | バンドルのマイグレーションの基底クラス。 |
| `kintai\Contract\Services\AuditLogger` | 安定 | 操作ログ。 |
| `kintai\Contract\Services\NotificationService` | 安定 | 通知（とプッシュ）。 |
| `kintai\Contract\Services\TranslationService` | 安定 | 翻訳。 |
| `kintai\Contract\Services\ImageCompressionService` | 安定 | 画像の圧縮。 |
| `kintai\Contract\Services\Mailer` | 安定 | インスタンスに設定された送信方法でメールを送ります：`send($to, $subject, $html, $attachmentPaths)`（契約1.15）。 |
| `kintai\Contract\Services\PdfCjkFontResolver` | 安定 | mPDF文書の日本語フォント：`PdfCjkFontResolver::applyTo($mpdfConfig)`（契約1.16）。 |
| `kintai\Contract\Services\Sales` | 安定 | 売上プロバイダーから読む店舗の日次売上（契約1.14、下の「売上」を参照）。 |
| `kintai\Contract\Services\ShiftWageCalculator` | 安定 | シフトの人件費（給与計算の唯一の算出元）。 |
| `kintai\Contract\Services\ShiftServiceInterface` | **暫定** | シフトの作成。対象を絞った書き込みAPIに置き換え予定（ステップ3）。 |
| `kintai\Contract\Services\StoreStatsServiceInterface` | **暫定** | 店舗の統計。プロバイダーに置き換え予定（ステップ3）。 |
| `kintai\Contract\View\ViewRenderer` | 安定 | バンドルのビューを描画。 |
| `kintai\Contract\View\Components\Badge` | 安定 | UIコンポーネント（`Badge`）。 |
| `kintai\Contract\View\Components\Button` | 安定 | UIコンポーネント（`Button`）。 |
| `kintai\Contract\View\Components\Card` | 安定 | UIコンポーネント（`Card`）。 |
| `kintai\Contract\View\Components\EmptyState` | 安定 | UIコンポーネント（`EmptyState`）。 |
| `kintai\Contract\View\Components\FilterBar` | 安定 | UIコンポーネント（`FilterBar`）。 |
| `kintai\Contract\View\Components\Flash` | 安定 | UIコンポーネント（`Flash`）。 |
| `kintai\Contract\View\Components\Form` | 安定 | UIコンポーネント（`Form`）。 |
| `kintai\Contract\View\Components\Modal` | 安定 | UIコンポーネント（`Modal`）。 |
| `kintai\Contract\View\Components\Pagination` | 安定 | UIコンポーネント（`Pagination`）。 |
| `kintai\Contract\View\Components\Table` | 安定 | UIコンポーネント（`Table`）。 |

### バンドル自身のクラス

バンドルはモデル、リポジトリ、サービス、テーブルを自分で持ちます。それらはもうCoreには一切ありません（すべての公式バンドルで計画のステップ4が完了）。バンドルがインポートするのは `kintai\Contract\…` だけで、契約利用テストに例外はもう残っていません。

### バンドルの確認

Kintaiの `tests/Support/ContractUsageScanner.php`（バンドルのPHPUnitハーネスが読み込みます）は、契約外の `kintai\Core\…`、`kintai\UI\…`、`kintai\Domain\…` への参照をすべて報告します：

```php
public function testOnlyTheContractIsUsed(): void
{
    $root = dirname(__DIR__);
    $this->assertSame([], ContractUsageScanner::violations(
        [$root . '/src', $root . '/Views', $root . '/routes.php'],
        [/* bundle-owned classes still living in the Core */],
    ));
}
```

## 拡張ポイント（契約1.1以降）

Coreがバンドルの名前を出すことはありません。バンドルはエントリクラスのメソッドをオーバーライドして、画面に追加するものを**宣言**し、Coreがそれを表示します。各バンドルは個別に問い合わせられます——例外や型の誤った値はそのバンドルの寄与だけを外してログに記録し、ページは動き続けます。使うには `"kintai_contract": "^1.1"` を指定してください。

### ナビゲーション：`navigation()`

```php
use kintai\Contract\Extension\NavItem;

public function navigation(): array
{
    return [
        new NavItem(
            key: 'timeoff',                       // unique, also the key of the user's "hide this entry" preference
            section: NavItem::REQUESTS,           // MAIN, PLANNING, HR, REQUESTS, REPORTS or SYSTEM
            label: 'timeoff',                     // translation key (manager view)
            managerRoute: 'admin.timeoff',        // route name, or null if managers have no page
            employeeRoute: 'employee.timeoff',    // route name, or null if employees have no page
            icon: 'leaf',
            employeeLabel: 'my_timeoff',
            activePaths: ['/admin/timeoff', '/employee/timeoff'],
            storeFeature: 'timeoff',              // per-store feature switch, if any
            order: 10,                            // Core entries use multiples of 10
            mobile: true,                         // may be picked for the mobile bottom bar
        ),
    ];
}
```

項目が表示されるのは、現在のロールにルートがあり、そのルートで宣言された権限を持ち、`storeFeature` が指定されている場合はその機能が有効なときだけです。ユーザーはナビゲーションの設定で項目を非表示にできます。

### 権限：`permissions()`（契約1.2）

```php
public function permissions(): array
{
    return [
        'timeoff' => ['view', 'create', 'update', 'delete', 'approve'],  // category => actions
    ];
}
```

各 `カテゴリ.アクション` の組は、ルートに設定できる権限キー（`permission: 'timeoff.approve'`）になり、バンドルが有効な間、オーナーがロールのページから付与します。名前は小文字の `snake_case` です。Coreのカテゴリ（`employees`、`shifts`、`stores`、`payroll`）は再定義できず、他のバンドルがすでに宣言したカテゴリは無視されます（ログに記録）。ロールのページでは、カテゴリは翻訳キー `perm_category_{カテゴリ}` で、アクションはよく使うもの（`view`、`create`、`update`、`delete`、`approve` など）はCoreのラベルで、それ以外はアクション名そのものを翻訳キーとして表示します——これらのキーをバンドルの `lang/` に含めてください。バンドルを無効にするとカテゴリはロールのページから消えますが、付与はデータベースに残り、再び有効にすると元に戻ります。バンドルの初回インストール時（セットアップアシスタントまたはカタログ）には、宣言されたすべての権限が店長ロールに付与され、店長は自分の店舗でそれを使います。アップデートでは再付与しないため、オーナーが取り消した権限は取り消されたままです。`"kintai_contract": "^1.2"` を指定してください。

### 店舗ごとの機能：`storeFeatures()`（契約1.3）

```php
use kintai\Contract\Extension\StoreFeature;

public function storeFeatures(): array
{
    return [
        new StoreFeature(
            key: 'timeoff',                       // stored in store_features, lowercase snake_case
            label: 'feature_timeoff',             // translation key of the card title
            description: 'feature_timeoff_desc',  // translation key of the card description
            icon: '🏖',
            order: 30,                            // Core features use multiples of 10
        ),
    ];
}
```

バンドルが有効な間、各機能は店舗フォームの「機能」カードにスイッチとして表示されます。従業員ページでは `HasStoreFeatureCheck::assertFeature($request, 'timeoff')` で確認し、同じキーを `NavItem` の `storeFeature` にも指定すると、機能を無効にした店舗では項目が消えます。機能を一度も保存していない店舗ではすべての機能が有効です。バンドルを無効にしても各店舗の選択は保持され、再び有効にすると元に戻ります。Coreや他のバンドルがすでに宣言したキーは無視されます（ログに記録）。`"kintai_contract": "^1.3"` を指定してください。

### ダッシュボードのウィジェット：`dashboardWidgets()`（契約1.4）

```php
use kintai\Contract\Extension\DashboardWidget;

public function dashboardWidgets(): array
{
    return [
        new DashboardWidget(
            key: 'team_notes',                    // unique on that dashboard, also the user's preference key
            audience: DashboardWidget::MANAGER,   // MANAGER ("/") or EMPLOYEE ("/employee")
            label: 'widget_team_notes',           // translation key shown in the "Customize" panel
            view: 'notebook::widgets.dashboard',  // your namespaced view (loadViewsFrom())
            data: fn(array $ctx): array => [      // variables passed to the view
                'entries' => $this->app->container()->make(NotebookEntryRepositoryInterface::class)
                    ->findVisibleForStores($ctx['store_ids']),
            ],
            permission: 'notebook.view',
            storeFeature: 'notes',
            order: 10,
        ),
    ];
}
```

ウィジェットはダッシュボードの「カスタマイズ」パネルに表示され（既定で有効）、Coreのウィジェットの後に全幅で描画されます。ユーザーが移動やサイズ変更をするとその並びと幅がユーザーごとに保存され、一度も配置していないウィジェットは最後に置かれます。表示されるのは `permission` を持ち、店舗で `storeFeature` が有効なユーザーだけです。`data` はコンテキストを受け取ります：`user`、`store_ids`（店長が管理する店舗、または従業員自身の店舗）、`managed_store_ids`（`null` = 全店舗、店長側）、`stores_map`（id => 名前）、`today`。ウィジェットに表示するものだけを読み込んでください——`data` はダッシュボードを表示するたびに実行されます。`data` やビューで例外が起きると、そのウィジェットだけが外れます（ログに記録）。完全なカード（`<div class="card card--mt">…</div>`）を描画してください。Coreのキーは再利用できません。以前Coreが描画していたウィジェットを引き継ぐバンドルは、ユーザーの選択が保たれるようそのキーを使い続けます（`notebook` は `team_notes`、`timeoff` は `pending_timeoff`、`shift-swap` は `pending_swaps`、`timeclock` は `timeclocks_today` と `timeclock` を抽出時に引き継ぎました）。`"kintai_contract": "^1.4"` を指定してください。

店長は自分の `EMPLOYEE` ウィジェットを店長ダッシュボードにも追加できます（既定では無効、「ウィジェットを追加」から選択。逆はできません）。その場合ウィジェットはキー `emp_{key}` で保存され、`data` は従業員ダッシュボードと同じコンテキストを受け取ります：店長自身の `store_ids`、`managed_store_ids` は `null`。そのためウィジェットのキーは41文字までに制限されています。

#### サイズと更新（契約1.26）

グリッドは 12 列です。任意の引数でウィジェットの見た目を宣言します（既定値は従来の動作：全幅、内容の高さ、更新なし）。

```php
new DashboardWidget(key: 'team_notes', /* … */ defaultWidth: 4, defaultHeight: 2, minWidth: 3, maxWidth: 8, refreshInterval: 120)
```

`defaultWidth` は列数（1〜12）、`defaultHeight` は最小の行数（1〜6）、`minWidth`/`maxWidth` はユーザーが選べる範囲、`refreshInterval` は秒
（0 = 更新なし、それ以外は 15 以上）です。幅の広いカードだけでなく、狭いカード（3〜4 列：一覧を省略し「すべて見る」へのリンク）にも対応するビューを作ってください。


### ビュー変数：`viewVariables()`（契約1.5）

```php
public function viewVariables(): array
{
    return [
        // name => fn(array $user): mixed, shared with every view of authenticated pages
        'unread_messages_count' => fn(array $user): int =>
            $this->app->container()->make(MessageRepositoryInterface::class)->countUnread((int) $user['id']),
    ];
}
```

ナビゲーションやレイアウトがすべてのページで必要とする少数の値——典型的には `NavItem` の `badge` で指定するカウンター——に使ってください。各関数は認証済みのすべてのページで実行されるため、軽いクエリ1つにとどめてください。名前は小文字の `snake_case` で、できればバンドル名を接頭辞にします。Coreの名前（`auth_user`、`managed_store_ids`、`store_features` など）や他のバンドルがすでに宣言した名前は拒否され、例外はその変数だけを外します（ログに記録）。`messaging` は `unread_messages_count` を、`daily-report` は `daily_report_staff_stores` をこの方法で共有しています。Core自身はどれも計算しません。`"kintai_contract": "^1.5"` を指定してください。

### アクションリンク：`actions()`（契約1.6）

```php
use kintai\Contract\Extension\Action;

public function actions(): array
{
    return [
        new Action(
            key: 'salary_report',
            slot: Action::EMPLOYEE_ROW,           // where the link goes, see below
            label: 'salary_report',               // translation key
            url: fn(array $ctx): ?string =>      // null hides the link for this context
                route_url('salary_reports.create', ['id' => $ctx['store_id']]) . '?user_id=' . $ctx['user_id'],
            icon: '💰',
            style: Action::DEFAULT,               // DEFAULT, PRIMARY or DANGER
            permission: 'payroll.generate',
        ),
    ];
}
```

スロットと、それぞれが `url` に渡すコンテキスト：

| スロット | 場所 | コンテキスト |
|---|---|---|
| `Action::EMPLOYEE_ROW` | スタッフ一覧の行の「⋮」メニュー | `user_id`、`store_id`（主な店舗） |
| `Action::EMPLOYEE_STORE` | 従業員フォームの「レポート」カード（店舗ごとに1回） | `user_id`、`store_id`、`store_name` |
| `Action::STORE_REPORT` | 店舗のスタッフレポートのヘッダー | `store_id` |
| `Action::STORE_REPORT_ROW` | 店舗のスタッフレポートの行と、その従業員の統計ページのヘッダー | `store_id`、`user_id`、`period` |
| `Action::SHIFTS_EMPLOYEE` | 従業員の予定のツールバー（一覧、カレンダー、日） | なし |

Coreはスロットのスタイルでリンクを描画し、`permission` を持つユーザーにだけ、`storeFeature` が指定されていればその機能が有効な場合にだけ表示します。スロット内ですでに使われているキーは無視され、`url` での例外はそのリンクを外します（ログに記録）。Core自身はリンクを追加しません。`shift-swap` は「交換を申請」をこの方法で提供しています。`"kintai_contract": "^1.6"` を指定してください。

### 通知と操作ログのラベル（契約1.7）

```php
public function notificationTypes(): array
{
    return ['timeoff_approved' => '✓', 'timeoff_refused' => '✗'];   // type => icon
}
```

バンドルが作成する通知の種類をアイコンとともに宣言します。ラベルはバンドルの `lang/` ファイルの翻訳 `notif_{type}` です。Coreの種類は再定義できません。すでに受信した通知は、翻訳が存在する限り、バンドルを無効にしても翻訳されたラベルを保ちます。

書き込む操作ログ（`AuditLogger`）も同じ方法でラベルが付きます：`lang/` ファイルに `audit_{action}` を含めてください。ドットとハイフンはアンダースコアに置き換えます（`timeoff.approved` → `audit_timeoff_approved`）。`notificationTypes()` には `"kintai_contract": "^1.7"` を指定してください。翻訳の慣例には何も必要ありません。

### 通知の種類：カテゴリ、チャネル、自動「既読」（契約1.23）

```php
public function notificationTypes(): array
{
    return [
        'timeoff_approved' => [
            'icon'           => '✓',
            'category'       => 'requests',                       // planning | requests | messages | reports | system
            'channels'       => ['push' => true, 'email' => true], // ユーザー設定の初期値
            'reference_type' => 'timeoff',                         // notify() に渡すIDが指す対象
        ],
        'timeoff_refused' => '✗',                                  // 旧形式：引き続き使用可能
    ];
}
```

拡張形式では、種類がユーザーの通知設定（プロフィール › 通知）に分類され、プッシュとメールの列の**初期値**が決まります。ユーザー自身の選択が常に優先され、ベルは常にすべてを受け取ります。旧形式 `type => icon` には、カテゴリ `system`、プッシュ有効、メール無効、`reference_type` なしが適用されます。不正な宣言は無視されてログに記録され、バンドルの読み込みを妨げることはありません。

`notify()` には必ず**正確なリンク**（一覧ではなく項目そのもの）と、`referenceId` として対象のIDを渡してください。

```php
$this->notifications->notify($userId, 'timeoff_approved', 'notif_timeoff_approved_body', ['name' => $name], $request->id, route_url('timeoff.show', ['id' => $request->id]));
```

ユーザーがその対象の詳細ページを開いたら、その通知を既読にします。

```php
$this->notifications->markReadFor($currentUserId, 'timeoff', $request->id);   // ID または ID の配列
```

`markReadFor()` は `kintai\Contract\Services\NotificationService` にあり、例外を投げないため、失敗してもページの表示は妨げられません。`referenceType` は種類で宣言したものです。`"kintai_contract": "^1.23"` を要求してください。

バンドルに詳細ページがない場合は、リンクを `?highlight={id}` 付きの一覧にし、対応する `.data-table` の行にクラス `tr--highlight`（Coreのスタイル）を付け、表示した行について `markReadFor()` を呼びます。

### 呼び出せるグローバル関数（契約1.24）

バンドルが呼び出せるグローバル関数は `src/Contract/functions.php` で宣言されたものだけです（`__`、`csrf_field`、`csrf_token`、
`csp_nonce`、`route_url`、`base_url`、`back_url`、`safe_redirect_path`、`store_segment`、`employee_segment`、`route_segment`、
`bundle_asset`、`translation_or`、`user_locale`、`month_label`、`date_label`、`format_currency`、`currency_symbol`、
`store_currency_style`、`style_class`、`inline_styles`。暫定：`bundle_asset_path`、`storage_path`）。Core のそれ以外のグローバル関数は
内部用で、予告なく変更される可能性があります。`ContractUsageScanner::functionViolations()`（バンドルの `ContractUsageTest` が使用）は、
PHP の関数でも宣言済みでもバンドル自身の定義でもない関数の呼び出しを報告します。Core のビューのパーシャルを `include` することも避け、
自前のものを用意してください。

### 翻訳：独自キー、Core のキー、上書き（契約1.25）

- 独自キーにはスラッグをプレフィックスとして付けます（`daily-report` → `daily_report_title`）。予約規約は `bundle_{slug}`、
  `bundle_{slug}_desc`、`notif_{type}`、`audit_{action}`、`cron_job_{名前}` です。3 言語（`fr`、`en`、`ja`）を同じ `:name`
  パラメータで用意してください。
- `src/Contract/translation-keys.php` に列挙された Core のキー（`save`、`cancel`、`delete`、月名など）は再定義せずに使えます。それ以外の
  Core のキーは内部用です。
- Core のキーの文言を変えるには、`lang/overrides/{locale}.json` に宣言します（同じ `:name` パラメータ）。宣言せずに `lang/{locale}.json` で
  Core のキーを再定義すると衝突となり、`TranslationContractChecker` が報告し、strict モードでは Core が無視します。オーナーによる編集
  （言語画面）が最優先で、次にバンドルの上書き、最後に Core です。
- `TranslationContractChecker::violations($bundleDir, $slug, $coreLangDir)` は規則別（`parity`、`placeholders`、`prefix`、`collisions`、
  `overrides`、`unknown`）に違反を返します。バンドルのテストから呼び出してください。

### 生成されたひな形から始める

```bash
php scripts/new-bundle.php my-feature --name="My feature"     # ../KintaiBundleDev/kintai-bundle-my-feature を作成
cd ../KintaiBundleDev/kintai-bundle-my-feature && composer install && vendor/bin/phpunit
```

ひな形は最初から契約に沿っています（プレフィックス付き翻訳、ナビゲーション、権限、ルート、ビュー、CSP 対応）。`tests/ContractTest.php` が
リリース前に実行する唯一の検査です（マニフェストのスキーマ、クラス、関数、翻訳）。チェックリスト：アイデア → 権限 → テーブルとマイグレーション →
拡張ポイント → ルートと RBAC → インラインコードのないビュー → 翻訳（fr/en/ja）→ テスト → ローカル登録 → インストールテスト → タグ付き GitHub リリース →
レジストリへの登録。



### 定期ジョブ：`cronJobs()`（契約1.8）

```php
use kintai\Contract\Cron\CronJob;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

final class SendRemindersJob implements CronJob
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function getName(): string
    {
        return 'timeoff-reminders';               // lowercase letters, digits, dashes
    }

    public function run(Request $request): Response
    {
        return Response::json(['ok' => true, 'sent' => $this->reminders->sendDue()]);
    }
}

// In your Bundle class:
public function cronJobs(): array
{
    return [SendRemindersJob::class];             // resolved by the container
}
```

バンドルが有効な間、外部のスケジューラー（cron-job.org、サーバーのcrontabなど）は、オーナーが作成したcronトークンを付けて `/cron/run/{名前}` を呼び出してジョブを実行します。トークンはできれば `Authorization: Bearer <トークン>` ヘッダーで渡してください。`?token=` のクエリ文字列はWebサーバーのアクセスログに残るためです。URLしか呼び出せないスケジューラー（共有ホスティングでよくある）のために `?token=` も引き続き使えます。SSHが使えるサーバーでは、ローカルのcrontabが `php scripts/cron.php {名前}` を実行します（トークン不要、追加のURLパラメーターは `--key=value` になります）。Coreの名前（`backup`、`log-purge`、`license-check`）やすでに使われている名前は拒否され（ログに記録）、生成できないクラスはスキップされます。`daily-report` は `auto-validate` をこの方法で提供しています。`"kintai_contract": "^1.8"` を指定してください。

契約1.20以降、ジョブのラベル（`php scripts/cron.php` で一覧表示）は、慣例により `lang/` ファイルの `cron_job_{名前}`（ハイフンはアンダースコアに置き換え、例：`cron_job_timeoff_reminders`）から取得されます。ない場合は名前が表示されます。

### プロフィールのセクション：`profileSections()`（契約1.12）

```php
use kintai\Contract\Extension\ProfileSection;

public function profileSections(): array
{
    return [
        new ProfileSection(
            key: 'directory',
            view: 'team-directory::profile-section',   // rendered with $user (the signed-in user's row)
            flags: ['show_in_directory', 'share_email', 'share_phone', 'share_mobile_phone'],
            order: 10,
        ),
    ];
}
```

バンドルが有効な間、Coreはプロフィールの「自分の情報」フォーム内、「保存」ボタンの前にビューを描画します。保存時には、宣言した `flags`（チェックボックス、`0`/`1`、リクエストにないフィールドは値を保持）を `users` テーブルに書き込みます——Coreがバンドルに開放している列（`ProfileSections::WRITABLE_USER_COLUMNS`）だけが受け付けられ、それ以外は無視されてログに記録されます。その他のデータは自分のテーブルとルートに置いてください。チェックを外せるよう、各チェックボックスの前に値 `0` の隠しフィールドを置いてください。すでに使われているキーは無視され、描画中の例外はそのセクションを外します（ログに記録）。Core自身はセクションを持ちません。`team-directory` は名簿のチェックボックスをこの方法で提供しています。`"kintai_contract": "^1.12"` を指定してください。

### Coreのイベント：`eventListeners()`（契約1.13）

```php
use kintai\Contract\Event\EmployeeCreated;

public function eventListeners(): array
{
    return [
        EmployeeCreated::class => function (EmployeeCreated $event): void {
            // $event->user (users row, without secrets), $event->storeId (?int), $event->createdBy (?int),
            // $event->source ('form', 'excel_import' or 'api')
        },
    ];
}
```

Coreは自分の側で起きたことを知らせ、Coreがバンドルを知らなくてもバンドルが反応できるようにします。イベントクラス（`kintai\Contract\Event\…`）ごとにリスナーは1つで、それ以外は無視されてログに記録されます。例外を投げたリスナーはログに記録され、Coreの処理や他のバンドルのリスナーを止めることはありません。利用できるイベント：

| イベント | いつ | データ |
|---|---|---|
| `EmployeeCreated` | アカウント作成の直後：「新しい従業員」フォーム、Excelインポートのクイック作成、`POST /api/v1/users` | `user`、`storeId`（従業員が割り当てられた店舗、なければ `null`——APIからは常に `null`）、`createdBy`、`source` |
| `ShiftSaved`（1.28） | シフトがリポジトリに書き込まれたとき（管理フォーム、API、Excelインポート、交換、募集シフトの引き受けなど経路を問わず） | `shift`（保存された行）、`before`（変更前の行。新規シフトでは `null`）。補助メソッド `isNew()`、`userId()`、`previousUserId()`（シフトを外された従業員） |
| `ShiftDeleted`（1.28） | シフトが削除されたとき（経路を問わず） | `shift`（削除前の行）。補助メソッド `shiftId()`、`userId()` |
| `OpenShiftClosed`（1.17） | 募集中に公開されたシフトがシフトフォームまたは `PUT /api/v1/shifts/{id}` から募集終了になったとき（`is_open` 1 → 0） | `shift`（保存された行）、`closedBy` |

`hiring-report` はこの方法で従業員の採用報告を作成し、`shift-claim` は募集終了したシフトの保留中の応募を取り下げます。`"kintai_contract": "^1.13"`（`OpenShiftClosed` には `^1.17`、`ShiftSaved`/`ShiftDeleted` には `^1.28`）を指定してください。

### ダッシュボードの統計カード：`dashboardStats()`（契約1.19）

```php
use kintai\Contract\Extension\DashboardStat;

public function dashboardStats(): array
{
    return [
        new DashboardStat('pending_swaps', 'swaps',
            fn(array $context): int => $this->countPendingFor((int) $context['user']['id']),
            icon: '🔄', tone: 'danger', storeFeature: 'swaps', order: 20),
    ];
}
```

Coreは従業員ダッシュボード上部の統計の列に、自分のカード（今日と今後のシフト）の後でカードを `order` 順に表示します。`value` はウィジェットと同じコンテキスト（`user`、`store_ids`、`stores_map`、`today`）を受け取り、表示する値を返します。`tone` は `DashboardStat::TONES` のいずれかです。`storeFeature` を指定すると、その機能が有効なときだけカードが表示されます。すでに使われているキーは無視され、例外はそのカードを外します（ログに記録）。Coreはバンドル用のカードを持ちません。`timeoff` と `shift-swap` はこの方法でカードを提供しています。`"kintai_contract": "^1.19"` を指定してください。

### シフトフォームのセクション：`shiftFormSections()`（契約1.17）

```php
use kintai\Contract\Extension\ShiftFormSection;

public function shiftFormSections(): array
{
    return [
        new ShiftFormSection(
            key: 'open_shift',
            view: 'shift-claim::shift-form-section',          // rendered with $shift, $mode ('create'|'edit'), $BASE_URL
            fields: ['is_open', 'open_shift_note'],
            actionsView: 'shift-claim::shift-form-actions',   // edit page only, next to "Save"
            storeFeature: 'open_shifts',
        ),
    ];
}
```

Coreはシフトフォーム（作成ページ、編集ページ、タイムラインのダイアログ）の中に `view` を描画し、編集ページではアクションバーに `actionsView` を描画します。どちらもシフトフォームの**内側**にあります：自分のルートを対象にするボタンは `formaction`/`formmethod`（と `formnovalidate`）を使ってください——ブラウザが無視する入れ子の `<form>` は使わないでください。保存時、Coreは宣言された `fields` だけを、バンドルに開放している列（`ShiftFormSections::WRITABLE_SHIFT_COLUMNS`：`is_open`、`open_shift_note`）の中からシフトに書き込みます。誰も宣言しないフィールドは無視されるため、バンドルなしにシフトを募集にすることはできません。`storeFeature` を指定すると、その機能が有効なときだけセクションが表示されます。すでに使われているキーは無視され、描画中の例外はそのセクションを外します（ログに記録）。Core自身はセクションを持ちません。`shift-claim` は募集シフトのフィールドとボタンをこの方法で提供しています。`"kintai_contract": "^1.17"` を指定してください。

### ページオーバーレイ：`pageOverlays()`（契約1.27）

```php
use kintai\Contract\Extension\PageOverlay;

public function pageOverlays(): array
{
    return [
        new PageOverlay(
            key: 'planning_check',
            view: 'planning-check::overlay',                         // 名前空間付きのビュー = ウィンドウの内容
            visibleWhen: fn(array $ctx): bool => /* サーバー側で判断：user, now, today, path, store_ids, can() */ true,
            data: fn(array $ctx): array => ['date' => $ctx['today']],
            permission: 'planning_check.submit',
            priority: 10,                                            // 小さいほど優先
            dismissible: true,                                       // Esc／外側クリック／[data-overlay-close]
            oncePerSession: false,
            placement: PageOverlay::DIALOG,                          // または PageOverlay::BAR：ページ下部の非ブロッキングバー
        ),
    ];
}
```

Core は表示可能なもののうち最も優先度の高い **1 つだけ** を、アクセシブルなダイアログ（`role="dialog"`、フォーカストラップ）として、ガード内で表示します。
`visibleWhen`、`data`、ビューで例外が起きるとそのウィンドウは取り除かれ、ログに記録されます。ログイン、印刷、シフト編集のページには表示されません。
ビューではウィンドウの見出しに `id="<?= $overlay_title_id ?>"` を付けてください。閉じると次のページまで非表示になり、再表示するかどうかはサーバーが決めます。
ウィンドウ内の操作はすべてバンドル自身のルートを通り、権限と店舗の範囲を確認します。オーバーレイは表示にすぎません。`"kintai_contract": "^1.27"` を要求してください。

## プロバイダー：Coreがバンドルから読むデータ（契約1.9以降）

Coreのページの中には、バンドルが持つデータを必要とするものがあります。Coreはバンドルのテーブルを読む代わりに、バンドルが実装する**プロバイダー**に問い合わせます。有効なプロバイダーがなければ、Coreは表示を減らすだけで、エラーにはなりません。

### 保留中の申請：`pendingRequestsProviders()`（契約1.9）

```php
use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;

final class PendingLeaveProvider implements PendingRequestsProvider
{
    public function __construct(private readonly LeaveRepository $leaves) {}

    public function key(): string { return 'timeoff'; }
    public function label(): string { return 'pending_timeoff'; }         // section title (translation key)
    public function emptyLabel(): string { return 'no_pending_request'; }
    public function permission(): string { return 'timeoff.view'; }
    public function storeFeature(): ?string { return 'timeoff'; }
    public function order(): int { return 10; }
    public function manageUrl(): string { return route_url('admin.timeoff'); }

    public function pending(?array $storeIds): array                      // null = every store
    {
        return array_map(fn(array $r) => new PendingRequest(
            id: (int) $r['id'], storeId: (int) $r['store_id'], userId: (int) $r['user_id'], createdAt: $r['created_at'],
            columns: [
                ['label' => 'user', 'value' => (int) $r['user_id'], 'style' => PendingRequest::USER],
                ['label' => 'type', 'value' => $r['type'], 'style' => PendingRequest::BADGE],
                ['label' => 'from', 'value' => $r['start_date']],
            ],
        ), $this->leaves->pendingForStores($storeIds));
    }
}

// In your Bundle class:
public function pendingRequestsProviders(): array
{
    return [new PendingLeaveProvider($this->app->container()->make(LeaveRepository::class))];
}
```

Coreはこれを「申請」ページ（プロバイダーごとに1セクション、列から組み立てた表）、ダッシュボードの「保留中の申請」カウンター、古い申請のアラートに使います——`permission` を持つユーザーに対してだけです。列のスタイル：`TEXT`、`BADGE`、`USER`（ユーザーID、名前で表示）、`STORE`（店舗ID、名前で表示）、`TIME`（`HH:MM`）。申請は表示順に返してください。例外はそのセクションだけを外します（ログに記録）。Core自身はプロバイダーを持ちません。`"kintai_contract": "^1.9"` を指定してください。
店長ダッシュボードの「古い申請」アラートもこれを読みます：3日を超えて保留中の申請が、プロバイダーの `label()` とともに表示されます。

### 欠勤：`absenceProviders()`（契約1.10）

```php
use kintai\Contract\Provider\Absence;
use kintai\Contract\Provider\AbsenceProvider;

final class LeaveAbsences implements AbsenceProvider
{
    public function absences(?array $userIds, ?array $storeIds): array   // null = no filter
    {
        return array_map(fn(array $r) => new Absence(
            (int) $r['id'], (int) $r['user_id'], (int) $r['store_id'],
            $r['start_date'], $r['end_date'],                              // Y-m-d, end included
            $r['type'], $r['status'],                                      // Absence::APPROVED, PENDING, REFUSED…
        ), $this->leaves->find($userIds, $storeIds));
    }
}
```

**すべての**ステータスの欠勤を返してください：日付とステータスの絞り込みはCoreが行います。Coreはこれを使って、承認済みの欠勤中に予定されたシフトを警告し、予定表、従業員のiCalフィード、欠勤率の統計に欠勤を表示します。宣言されたすべてのプロバイダーの欠勤は合算され、失敗したプロバイダーはスキップされます（ログに記録）。プロバイダーがない場合（`timeoff` が無効またはアンインストール）、Coreには欠勤が見えません。`"kintai_contract": "^1.10"` を指定してください。

### 出勤記録：`attendanceProviders()`（契約1.11）

```php
use kintai\Contract\Provider\Attendance;
use kintai\Contract\Provider\AttendanceProvider;

final class ClockAttendance implements AttendanceProvider
{
    public function attendance(?array $userIds, ?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        return array_map(fn(array $r) => new Attendance(
            (int) $r['id'], (int) $r['user_id'], (int) $r['store_id'],
            $r['shift_date'],                  // Y-m-d, the day worked ($from/$to filter on it, inclusive)
            $r['clock_in'],                    // Y-m-d H:i:s
            $r['clock_out'],                   // Y-m-d H:i:s, or null while still clocked in
        ), $this->clocks->find($userIds, $storeIds, $from, $to));
    }
}
```

Coreはこれを店長ダッシュボードで使います（開いたままの打刻、予定シフトと比べた遅刻）。宣言されたすべてのプロバイダーの記録は合算され、失敗したプロバイダーはスキップされます（ログに記録）。プロバイダーがない場合（`timeclock` が無効またはアンインストール）、Coreには打刻が見えません。`"kintai_contract": "^1.11"` を指定してください。

### 売上：`salesProviders()`（契約1.14）

```php
use kintai\Contract\Provider\DailySales;
use kintai\Contract\Provider\SalesProvider;

final class ReportSales implements SalesProvider
{
    public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
    {
        return array_map(fn(array $r) => new DailySales(
            $r['report_date'],                 // Y-m-d ($from/$to inclusive)
            $r['status'],                      // filter on $statuses (when given) before anything else
            (float) $r['sales_total'],         // the day's value, not a running total
            (float) $r['labor_cost'],
            (int) $r['customer_count'],
            (float) $r['waste_total'],
        ), $this->reports->forStore($storeId, $from, $to, $statuses));
    }
}
```

Coreは店舗の収益性ページでこれを読み、バンドルは `kintai\Contract\Services\Sales` を通じて読みます（`dailySales($storeId, $from, $to, $statuses)` は日付順の行を返します：`report_date`、`status`、`sales_total`、`labor_cost`、`customer_count`、`waste_total`）——`salary-report` はこの方法で売上合計を事前入力します。データが累計で入力されている場合は、ステータスの絞り込みの後で、その日の値に自分で変換してください。宣言されたすべてのプロバイダーの日付は統合され、失敗したプロバイダーはスキップされます（ログに記録）。有効なプロバイダーがなければ売上はなく、Coreは店舗の収益性ページを非表示にします。売上プロバイダーは `daily-report` です。`"kintai_contract": "^1.14"` を指定してください。

### シフト交換：`swapProviders()`（契約1.18）

```php
use kintai\Contract\Provider\Swap;
use kintai\Contract\Provider\SwapProvider;

final class RequestSwaps implements SwapProvider
{
    public function swaps(?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        return array_map(fn(array $r) => new Swap(
            (int) $r['id'], (int) $r['store_id'],
            (int) $r['requester_id'], (int) $r['target_user_id'],
            $r['created_at'],                  // Y-m-d H:i:s ($from/$to filter on its date, inclusive)
            $r['status'],                      // every status, the Core filters what it needs
        ), $this->requests->find($storeIds, $from, $to));
    }
}
```

Coreはこれで従業員の交換を数えます（店舗のスタッフレポートの「交換」列、従業員の統計ページ）。宣言されたすべてのプロバイダーの交換は統合され、失敗したプロバイダーはスキップされます（ログに記録）。プロバイダーがない場合（`shift-swap` が無効またはアンインストール）、数える交換はありません。`"kintai_contract": "^1.18"` を指定してください。

## RBACの範囲の契約：`managed_store_ids`

バンドルが `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` の下に登録するWebルートは、すべて `permission:` を宣言する必要があります——`PermissionCatalog` の正確なキーか、`'public'`（セルフサービスや集計ページなど、意図的に制限しないルート。`permission:` を省略しても同じ動作）です。`PermissionMiddleware` はこれをリクエスト属性 `managed_store_ids` に解決し、以降のすべてのコントローラーは再計算せずにこれを読むことが期待されます：

- **`null`** — 制限なし。ユーザーがオーナーであるか、ロールがルートの権限キーを「全店舗」範囲付きで付与している（`role_permissions.scope = 'global'`）かのどちらかです——後者はロールへの*割り当て*自体が自店舗に限定されていても起こりえます。どちらも意味はまったく同じで、店舗による絞り込みはありません。
- **`int[]`** — これらの店舗IDに厳密に限定。

**このバグを毎回再発させる間違い**：`=== null` で分岐する代わりに `$request->getAttribute('managed_store_ids') ?? []` と書くことです。Coreのいくつかのリポジトリメソッドは*空の*フィルター配列を「フィルターなし」として扱うため、下流の一覧クエリが偶然「動いて」しまい無害に見えますが、同じ潰れた値を次に読むもの（店舗の選択肢、`in_array($storeId, $managedIds)` によるアクセス確認）は「全店舗」ではなく「店舗なし」と見なして壊れます。まさにこの間違いが `kintai-bundle-daily-report` の `DailyReportController::indexAll()` で出荷されました（`fix/global-scope-permission-store-filter` で修正）——`null` は `is_admin` と同じく常に独立した分岐として扱い、`??` のフォールバックで吸収できるものとして扱わないでください。

この解決ロジックを自分で再実装しないでください。契約のトレイト `kintai\Contract\Http\Concerns\HasAdminAccess`——`managedIds(Request $request): ?array`、`availableStores(?array $managedIds): array`、`assertStoreAccess(Request $request, int $storeId): void`、`assertAnyStoreAccess(Request $request, array $storeIds): void`——を再利用してください。すべての公式バンドルの管理コントローラーが使っているトレイトです（実例は `kintai-bundle-store-photos` の `StorePhotoController` を参照）。これは `null` の場合をすでに正しく扱っています。自分のコントローラーで手書きした同等品こそ、この種のバグがバンドルごとに再発する原因です。

バンドルが同じ認可のもとで**アップロードされたファイル**（画像、PDF、添付ファイル）を配信する必要がある場合は、独自のファイル配信ルートを作らないでください——Kintaiの `/storage/{path*}`（ルート名 `storage.file`）にリンクしてください。これは `managed_store_ids`（`StorageFileController::assertPathStoreAccess()`）に加えて、アップロードパスの閉じ込めとMIMEの許可リストをすでに適用しています。並行するファイル配信ルートは、Coreがすでに持つ認可ロジックをCoreのテストの外で重複させるだけです。

## 読みやすいURL：型付きルートパラメーター

CoreのWebルートは、店舗と従業員についてデータベースのIDをもう表示しません（`/admin/stores/所沢東町店/edit`、`/admin/users/057/edit`、`docs/architecture.md` の「Readable URLs」を参照）。バンドルはルートパラメーターに型を付けるだけで同じものを得られます——登録は不要です：

```php
// routes.php
$r->get('/stores/{id:store}/photos',               [StorePhotoController::class, 'index'], name: 'store_photos.index', permission: 'photos.view');
$r->get('/stores/{id:store}/reports/{uid:employee}', [ReportController::class, 'show'],    name: 'reports.show',       permission: 'reports.view');
```

- **コントローラーは変わりません：** `$request->param('id')` は引き続き数値IDを返します。セグメントは `PermissionMiddleware` の前に解決されるため、`store_param` のルールと `assertStoreAccess()` は変更なしで動き続けます。
- **リンクは `route_url()` にIDを渡して生成してください**（`route_url('store_photos.index', ['id' => $storeId])`）：読みやすくエンコードされたセグメントが出力されます。手で組み立てるURLには、生のIDの代わりに `store_segment($storeId)` / `employee_segment($userId)` を使ってください——従業員の名前は決して使わないでください。
- **古いリンクも引き続き使えます：** 数値IDは解決され、GETは読みやすいURLへ `301` でリダイレクトされます（POSTはそのまま処理されます）。そのため、バンドルの移行前に送られたブックマーク、メール、通知は壊れません。
- **型を付けるのは店舗または従業員を指すものだけです。** レポート、写真、所属のIDは単なる `{id}` のままです。
- **型付きルートパラメーターを備えたCoreが必要です**（CHANGELOGを参照）：そのようなCoreが提供する `kintai_contract` を指定してください。古いCoreは `{id:store}` を文字どおりのセグメントとして読んでしまいます。

## データベースのマイグレーション

バンドルは独自のテーブルを持てます——スキーマを作るためだけにKintai本体のリポジトリへ専用のPRを出す必要は**もうありません**。バンドルのルート（`src/` と並べて、`Views/`/`routes.php` と同じ規則）に任意の `database/migrations/` フォルダを置き、Coreのマイグレーション（`database/migrations/php/*.php`）と**まったく同じ形式**のファイルを入れてください：

```php
<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('your_bundle_table')) {
            return;
        }
        $this->schema()->create('your_bundle_table', function (Blueprint $table) {
            $table->increments('id');
            // ...
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('your_bundle_table');
    }
};
```

ファイル名は `YYYY_MM_DD_NNNNNN_description.php` とします——Coreのマイグレーションと同じく、アルファベット順が実行順です。`up()` は必ず `hasTable()`/`hasColumn()` で保護してください（上の例のとおり）：手動の再同期で再実行されることがあるため、マイグレーションは冪等でなければなりません（下記参照）。必ず `$this->schema()` を通してください：SQLiteとMySQLの両方で、テーブルにCoreと同じ移植性のあるスキーマ（参照する `integer()` 列と一致する符号付き主キー）が与えられます。`text()` 列の既定値には `$this->textDefault('…')` を使ってください——MySQLはそこで単純なリテラルを受け付けません。

**実行されるタイミング**：`/admin/bundles/market` からのインストールまたはアップデートで、バンドルのファイルがディスクに配置された直後に自動で実行されます（`BundleInstallerService::activate()`）。バンドルが有効になる前なので、マイグレーションが失敗するとスキーマが不完全なバンドルを有効にするのではなく、インストールやアップデート全体が中止されます。`php scripts/db-migrate.php` で、インストール済みの全バンドルをまとめて手動で再同期することもできます（`--dry-run` で保留中のものを確認、Coreと同じオプション）。

**追跡**：適用されたバンドルのマイグレーションは `bundle_migrations` テーブル（`bundle_slug` + `migration` 名、組み合わせで一意）に記録されます——Coreの `migrations` テーブルとは別なので、2つのバンドルがマイグレーションのファイル名で衝突することはなく、`BundleMigrationRunner::forgetBundle()` はアンインストール時にそのバンドルの追跡行だけを消去できます。業務テーブル自体はアンインストール時に削除**されません**（「制限事項」を参照）——追跡行だけが消えるため、後で再インストールすると `up()` がきれいに再実行されます（テーブルが残っていれば `hasTable()` のガードで何も起きません）。

リポジトリのインターフェース、Eloquentモデル、テーブルは、すべての公式バンドルと同様にバンドル自身の名前空間に置きます（上の「バンドル自身のクラス」を参照）。

## アセット

バンドルに独自のCSSやJSが必要な場合、Kintai本体の `public/assets/` への追加を求めないでください——この仕組みができる前はどのバンドルもそうしており、バンドルを別リポジトリで配布する意味がまったくありませんでした（CSSの微調整のたびにCoreのPRが必要でした）。自分の `public/` フォルダ（`src/`、`Views/`、`routes.php` と並べて——上の必要な構成と同じ規則）から自分で提供してください：

```
your-bundle/
  public/
    css/your-bundle.css
    js/your-bundle.js
```

`loadViewsFrom()`/`loadRoutesFrom()` とまったく同じように `register()` で登録します：

```php
public function register(): void
{
    $this->loadViewsFrom($this->getPath() . '/Views', 'your-namespace');
    $this->loadRoutesFrom($this->getPath() . '/routes.php');
    $this->loadAssetsFrom('public');
}
```

バンドルのどのビューからも、ファイルはヘルパー `bundle_asset()` で参照してください——実際の場所はインストールされているバージョンによって変わるため、パスをハードコードしないでください：

```php
<?php if ($css = bundle_asset('your-slug', 'css/your-bundle.css')): ?>
<link rel="stylesheet" href="<?= $css ?>">
<?php endif; ?>
```

`bundle_asset()` はバンドルが有効でないとき `null` を返します（例外は投げません）——常に解決されると決めつけず、上のように `<link>`/`<script>` を `if` で保護してください。生成されるURL（`GET /bundle-assets/{slug}/{path}?v={version}`）は、認証のない専用ルート（`BundleAssetController`）で配信されます——これらは公開の静的ファイルで、バンドルのページのように `AuthMiddleware`/`PermissionMiddleware` で保護されません——宣言した `public/` フォルダに閉じ込められ、固定の拡張子の許可リスト（`css`、`js`、`svg`、`png`、`webp`）に限られます。許可リスト外のもの、または `public/` フォルダより上に遡ろうとするものは拒否されます（`403`）。無効なバンドルや `loadAssetsFrom()` を一度も呼んでいないバンドルへのリクエストは、単なる `404` です。

ビューがURLではなくファイルの**内容**を必要とする場合——よくあるのは `file_get_contents()` でスタイルシートを埋め込むPDF出力——は代わりに `bundle_asset_path()` を使ってください。同じファイルの絶対パスを返します（無効なら同じく `null`）：

```php
$css = file_get_contents(bundle_asset_path('your-slug', 'css/pdf-your-bundle.css') ?? '');
```

## マニフェスト `bundle.json`

バンドルのリポジトリのルートに必須です：

```json
{
    "slug": "notebook",
    "name": "Carnet de notes",
    "version": "1.0.0",
    "description": "Team notebook — notes shown on the manager and employee dashboards, pinning, automatic expiry.",
    "author": { "name": "Your Name", "email": "you@example.com", "url": "https://example.com" },
    "kintai_contract": "^1.19",
    "requires_bundles": {},
    "namespace": "kintai\\Bundles\\Installed\\Notebook",
    "entry_class": "kintai\\Bundles\\Installed\\Notebook\\NotebookBundle",
    "license": "AGPL-3.0-only",
    "homepage": "https://github.com/you/your-bundle"
}
```

| フィールド | 必須 | 備考 |
|---|---|---|
| `slug` | はい | `getName()` の戻り値と一致する必要があります——インストール時に確認され、一致しないスラッグはディスクに何かを書く前に拒否されます。 |
| `name` | はい | 人が読める名前で、カタログに表示されます。 |
| `version` | はい | 厳密なsemver（`X.Y.Z`）で、`version_compare()` で比較されます。`getVersion()` の戻り値と一致する必要があります。 |
| `namespace` | はい | `src/` 以下すべてのPSR-4ルート。 |
| `entry_class` | はい | エントリクラスの完全修飾名。`namespace` から解決したファイルが `src/` 以下に存在する必要があります（有効化の前に確認）。 |
| `description`、`author`、`license`、`homepage` | いいえ | 情報のみ。 |
| `kintai_contract` | はい | **バンドル契約**のバージョンに対するsemver制約（[バージョン付きバンドル契約](#バージョン付きバンドル契約)を参照）、例：`^1.19`。これが唯一の互換性チェックです：これを持たないバンドルはバージョン付き契約より前のもので、インストール時に拒否され、起動時にも読み込まれません。 |
| `kintai_core.min`/`.max` | いいえ — **情報のみ** | 契約が必須になって以降、互換性チェックでは無視されます（それ以前に公開されたバンドルは範囲を無制限に宣言しており、Coreが内部クラスを削除すると壊れていました）。 |
| `requires_bundles` | いいえ（既定は `{}`） | スラッグ → バージョン制約。**今のところ情報のみ**——「制限事項」を参照。 |

## バージョン付きバンドル契約

バンドル契約には、Coreとは独立した**独自のバージョン**があります：`kintai\Contract\Contract::VERSION`（現在 `1.23.0`）。semverに従い、マイナーバージョンは追加だけ（クラス、任意のメソッド、拡張ポイント）を行い、メジャーバージョンはシグネチャを削除・変更し、CHANGELOGの専用の「Bundle contract」セクションで告知されます。

バンドルが必要とする契約のバージョンを `bundle.json` に宣言してください：

```json
"kintai_contract": "^1.0"
```

使える制約（Composerの規則）：`^1.2`（>= 1.2.0, < 2.0.0）、`~1.2` / `~1.2.3`、`>=1.0 <2.0`（スペース = AND）、`^1.0 || ^2.0`（OR）、`*`、厳密なバージョン。

Coreはこれを次のタイミングで確認します：

- **インストール時**（カタログ）：互換性のないバンドルは、ディスクに何かを書く前に拒否されます。
- **起動時**：互換性がなくなったインストール済みバンドル（例：契約のメジャーバージョンを上げたCoreのアップデート後）は、有効でも**読み込まれません**——アプリケーションは動き続け、イベントがログに記録され（`bundle_incompatible_not_loaded`）、バンドルのページに理由が表示されるので、オーナーは互換性のあるバージョンをインストールできます。
- **Coreのアップデート前**：`/admin/update` は提供されるリリースの契約バージョンを読み、それでは読み込まれなくなるインストール済みバンドルを一覧表示します。

`kintai_contract` のないバンドルは、`src/Bundles/{Name}/` に手で置いたもの（クラスの隣に `bundle.json` が必要）も含め、どこでも互換性なしとして扱われます。

`kintai_core.min`/`max` は情報のみです（上のマニフェストの表を参照）：唯一の互換性チェックは `kintai_contract` です。

## リリースの公開

1. `bundle.json` の `version`（とエントリクラスの `getVersion()`——両者は一致する必要があります）を上げます。
2. コミットに `vX.Y.Z` タグを付けてプッシュします。
3. そのタグのGitHub Releaseを作成します（`gh release create vX.Y.Z --generate-notes`、またはタグのプッシュ時に同じことをするCIワークフロー——`kintai-bundle-notebook` の `.github/workflows/release.yml` を参照）。ビルドは不要です：Releaseが自動生成する `zipball_url` こそ `BundleInstallerService` がダウンロードするものです。

## Content Security Policy：インラインスクリプト禁止

Kintaiは `script-src 'self' 'nonce-…'`（`SecurityHeadersMiddleware`）を送ります：ブラウザが実行するのは、Kintai自身が配信するスクリプトか、現在のリクエストのnonceを持つインラインの `<script>` だけです。**インラインのイベント属性（`onclick=`、`onchange=`、`onsubmit=`、`oninput=` など）と `javascript:` リンクはブロックされます**。しかも静かに——ボタンは何もしなくなるだけで、サーバー側にエラーは出ません。したがって、バンドルのビューでこれらを使ってはいけません。

- **インラインの `<script>`** — 本当に必要なときだけ、リクエストのnonce付きで：`<script nonce="<?= csp_nonce() ?>">…</script>`。データブロック `<script type="application/json">` は実行されないのでnonceは不要です。何も必要としない、バンドルの `assets/js/` のファイル（`bundle_asset()` で読み込み）を優先してください。
- **イベント属性** — `public/assets/js/modules/csp-actions.js`（アプリのレイアウトが読み込み）が処理する宣言的な `data-*` 属性に置き換えてください：

| 代わりに | こう書く |
|---|---|
| `onclick="doThing()"` | `data-on-click="doThing"` |
| `onclick="doThing('a', 2)"` | `data-on-click="doThing" data-args='["a", 2]'` |
| `onchange="doThing(this.value)"` | `data-on-change="doThing" data-args='["@value"]'`（`"@this"`、`"@value"`、`"@checked"` は呼び出し時に置き換えられます） |
| `onchange="this.form.submit()"` | `data-submit-on-change` |
| `onchange="document.getElementById('f').submit()"` | `data-submit-form="f"` |
| `onclick="location.href='/x'"` | `data-goto="/x"`（http/httpsのみ） |
| `onclick="event.stopPropagation()"` | `data-stop-propagation` |
| `onclick="window.print()"` | `data-on-click="@print"`（`@close`、`@select`、`@removeParent`、`@copy` も） |
| `onsubmit="return confirm('…')"`、`onclick="return confirm('…')"` | `<form>` または送信ボタンに `data-confirm="…"`（共通の確認モーダル） |
| `Button::attrs(['onclick' => 'f()'])` | `Button::attrs(['data-on-click' => 'f'])` |

`data-on-*` が呼び出すのは、`window` 上に**自分で定義した関数**（スクリプト内の関数宣言、または `window.f = …`）だけです：`eval` や `setTimeout` などのブラウザ組み込み関数は、注入された属性がコード実行の手段にならないよう意図的に拒否されます。`Button`/`Modal` の属性値はコンポーネントがすでにHTMLエスケープしています——`htmlspecialchars()` の結果ではなく生の値を渡してください。

**必要なバージョン。** `data-*` アクションと `csp_nonce()` は **Kintai Core 0.3.0** から存在します。バンドル契約を提供するCoreはすべて（1.0はCore 0.3.6で登場）これらを持っているため、現在必須の `kintai_contract` を宣言すれば十分です：古いCoreは、何もしないコントロールをインストールする代わりにバンドルを拒否します。逆に、これは**既存バンドルにとって破壊的な変更**です：0.3.0より前に書かれ、まだインラインハンドラーを使っているバンドルは、0.3.0より古いCoreでは動き続けますが、0.3.0以降では移行するまでボタン、セレクト、確認が動かなくなります。Kintaiの `tests/Unit/Security/NoInlineScriptGuardTest.php` は、Coreのビューがインラインハンドラーを再び持ち込んだ時点で失敗します。自分のビューでも同じ確認をしてください。

### 動的スタイル：`style="…"` 禁止

契約 1.22 以降、スタイルにも同じルールが適用されます。CSP は `style-src 'self' 'nonce-…'`（`'unsafe-inline'` なし）を送るため、`style="…"` 属性はブラウザに無視され、`<style>` ブロックは nonce 付きの場合にだけ適用されます。スクリプトと同じく、エラーは出ません。**1.22 より前に書かれ、まだ `style="…"` を使うバンドルには破壊的変更です**：移行するまで色、幅、位置が失われます。固定の見た目はバンドルの CSS に書きます。描画時にしか分からない値（ユーザーが選んだ色、バーの高さ、割合）は `style_class()`（契約 1.22）を使います。これらの宣言を持つクラスを返します：

```php
<div class="bar <?= style_class(['height' => $h . 'px', 'background' => $color]) ?>"></div>
<span class="avatar-chip <?= style_class(['--chip-bg' => $user['color']]) ?>"></span>
```

ルールはリクエスト中に集められ、レイアウトが 1 つの `<style nonce="…">` にまとめて出力します。レイアウト**なし**で描画するページ（印刷ビュー、PDF プレビュー）は `</body>` の直前で `<?= inline_styles() ?>` を自分で出力し、自前の `<style>` ブロックには nonce を付けます：`<style nonce="<?= csp_nonce() ?>">`。値はフィルタされます（英字、数字、`#`、`%`、`.`、`,`、`-`、空白、括弧のみ）。それ以外は捨てられるため、入力データがルールの外に出ることはありません。JavaScript から `element.style.x` を設定するのは CSP の対象外で引き続き使えますが、JavaScript で `style="…"` 属性付きの HTML を組み立てるのは不可です。

### 既存バンドルの移行

1. 問題箇所を探します：`grep -rnE "[[:space:]]on(click|change|submit|input)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|javascript:|<script>" Views src`。
2. それぞれを上の表の対応する `data-*` 属性に置き換えます。`confirm()` は `data-confirm` になり、`event.stopPropagation()` を呼んでいたハンドラーには `data-stop-propagation` が必要です（そうしないと委譲された祖先も発火します）。
3. `data-on-*` で呼ぶ関数がすべて **`window` 上に**定義されていることを確認します：通常のスクリプト内の関数宣言、または `window.f = …`。トップレベルの `const f = …` や `let f = …` は `window` のプロパティではないため拒否されます。
4. 残すインラインの `<script>` ブロックにnonceを付けます：`<script nonce="<?= csp_nonce() ?>">`。
5. `kintai_contract` を宣言し、下のCIチェックを追加します。

### CIでの確認

ブラウザがインラインハンドラーをブロックしてもサーバーにはエラーが見えないため、ほかに退行を見つけるものはありません。公式バンドルは `.github/workflows/tests.yml` でこのステップを実行しています：

```yaml
- name: No inline event handlers or nonce-less scripts (Kintai CSP)
  run: |
    set -euo pipefail
    if grep -rnE "[[:space:]]on(click|change|submit|input|load|error|focus|blur|dblclick|keyup|keydown)[[:space:]]*=|['\"]on(click|change|submit|input)['\"][[:space:]]*=>|(href|src|action)[[:space:]]*=[[:space:]]*[\"']javascript:|<script>" Views src; then
      echo "::error::Inline handlers, javascript: URLs and <script> without nonce are blocked by Kintai's CSP"
      exit 1
    fi
- name: No inline styles (Kintai CSP)
  run: |
    set -euo pipefail
    if grep -rnE "[[:space:]]style[[:space:]]*=[[:space:]]*[\"'\\\\]|<style>" Views; then
      echo "::error::style=\"...\" attributes and <style> without nonce are ignored by Kintai's CSP"
      exit 1
    fi
```

**レジストリ**は、通常のHTTPSで配信される静的な `registry.json` ファイルにすぎません（GitHubリポジトリの `raw.githubusercontent.com` のURLが適しており、公式のものもそうして配信されています）——Kintaiはこれもクローンせず、GETで取得するだけです（`BundleRegistryClient`）。

```json
{
    "schema_version": 2,
    "name": "My registry",
    "bundles": [
        {
            "slug": "your-bundle",
            "name": "Your Bundle",
            "description": "...",
            "repository_url": "https://github.com/you/your-bundle",
            "versions": {
                "release": ["1.0.0"],
                "beta": ["1.1.0", "1.0.0"],
                "alpha": ["1.1.0", "1.0.0"]
            }
        }
    ]
}
```

- `schema_version` — Kintaiが現在理解するのは `1` と `2` です。より新しいスキーマを理解できないインスタンスは、誤って解釈する代わりに一覧をきれいに拒否します（ログに記録）。
- `repository_url` は単純な `https://github.com/{owner}/{repo}` である必要があります——インストーラーはここからGitHub APIのリリース検索URLを導きます。
- `versions`（スキーマ2）はアップデートチャンネルをキーにしています——`release`（`main` から公開されたプレリリースでないリリースのみ）、`beta`（`main` または `beta`、`alpha` を除く）、`alpha`（すべて）——それぞれインストール可能なバージョンを新しい順に並べたリストです。これは、オーナーが `/admin/bundles/market` で全インストール済みバンドルに対して一度選ぶチャンネル（`AppSettingsService::bundleUpdateChannel()`、Core自身の `/admin/update` のチャンネルとは独立）に対応しています：そのチャンネルに合うリストが、カタログに「最新」として表示され、通常のアップデートでインストールされるものです。**スキーマ1**（チャンネルのキーがない平らな配列の `versions`）も互換性のために受け付けます——その場合、どのリリースがどのブランチから来たか判断できないため、Kintaiはすべてのチャンネルで同じ平らなリストを提示します。公式レジストリは、スキーマ2の3つのリストを各バンドルの実際のGitHub Release（`target_commitish`/`prerelease`、Kintai自身のリリースチャンネルと同じ規則）から自動で計算します——[`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle) の `scripts/sync-versions.js` を参照してください。これは1時間ごとに実行され、新しいリリースでいずれかのバンドルのバージョン一覧が変わるとPRを開きます。`versions` を手で編集することはありません。
- リポジトリ内の `bundle.json` は、互換性（`kintai_contract`）とインストール時に読まれるその他すべての実際の情報源であり続けます——ここの `versions` は、何がどのチャンネルでインストール可能かをカタログに示すヒントにすぎません。
- `commits`（任意、バージョンを上げずにスキーマ2に追加——古いKintaiは無視します）は、各バージョンをそのタグが指すコミットの40文字の完全なshaに対応付けます。例：`"commits": {"1.0.2": "7f99e084…"}`。これがある場合、Kintaiはダウンロードしたアーカイブを**何も展開する前に**照合し（GitHubのzipballはZIPのコメントとルートフォルダ名にコミットを持っています）、不一致ならインストールを拒否します。そのため、別のコミットに移されたタグ（リポジトリやアカウントの乗っ取り）がレジストリの裏で別のコードを紛れ込ませることはできません。公式レジストリはこれを自動で埋めます：同じ1時間ごとの `sync-versions.js` の実行が新しいリリースのコミットを固定し、既存の固定を書き換えることはなく、タグが動いていれば明示的に失敗します——固定が `main` に届くのは、メンテナーが確認するプルリクエストを通じてだけです。Kintaiは期待されるコミットをサーバー側でレジストリから読み（リクエストからは決して読まない）、確認のためにレジストリに到達できなければインストールを拒否します。`commits` を公開しないレジストリは従来どおり動きます（警告がログに記録されます）。これは署名ではありません：GitHubによるアーカイブの生成とHTTPSに依存しており、信頼の起点はあくまでレジストリ自体です。自分のレジストリを管理する場合は、`AudricSan/KintaiBundle` の `sync-versions.js` をそのまま再利用できます。

見つけてもらう方法は2つです：
- **自分のレジストリ** — `registry.json` を自分で書いてホストし（HTTPSで到達できればどこでも）、誰でも `/admin/bundles/registries` からそのURLを追加できます。誰の承認も必要ありません。
- **Kintai公式レジストリ**（[`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)） — その `registry.json` に項目を追加するプルリクエストを開いてください。そこに載っても、バンドルが「公式」になるわけでは**ありません**——下記参照。

## 公式とサードパーティ

Kintai本体のリポジトリにある `config/official-bundles.php` が、Kintaiプロジェクト自身が保守するスラッグについての**唯一の**情報源です——どのレジストリに載っていても、バンドルが自分を公式と宣言することはできません。それ以外はカタログで「サードパーティ」と表示され、インストールやアップデートには `confirm_third_party` チェックボックスの明示的な確認が必要で、これはサーバー側で強制されます（JavaScriptで隠すだけではありません）——ユーザーには警告が表示されることを想定し、先に進む前にそれが読まれる前提でバンドルを設計してください。

## 既知の制限事項（執筆時点）

- **1つのプロセス、1つのComposerオートローダー。** バンドルごとの `composer.json` や依存関係の分離はありません——バンドルはKintai本体の `kintai\` ルートと同じPHPプロセス、同じ名前空間ツリーで動きます。上に挙げた `kintai\Contract\…` の名前だけに依存してください：Kintaiのバージョンをまたいで保証されるCoreのクラスはそれだけです。
- **`requires_bundles` はまだ強制されません。** 他のバンドルのスラッグやバージョンへの依存を宣言すると受け付けて保存されますが、それが欠けていたり古すぎたりしても、現在インストールを止めるものはありません——今のところ保証ではなくドキュメントとして扱ってください。
- **業務データのアンインストールはありません。** `/admin/bundles/market` はバンドルのファイル、マニフェストの項目、マイグレーションの追跡行のインストール、アップデート、アンインストールができますが、マイグレーションが作成したテーブル（とそのデータ）はアンインストール時に削除も変更もされません。必要ならその掃除は、今のところデータベースでの手作業です。
- **インラインのスクリプトとイベントハンドラーは動きません。** Kintai Core 0.3.0以降、Content-Security-Policyにはスクリプト用の `'unsafe-inline'` がありません：`onclick=`/`onchange=`/`onsubmit=`/`oninput=`、`javascript:` リンク、nonceのない `<script>` を含むバンドルのビューは、ブラウザによって静かにブロックされます。契約 1.22 以降はスタイルも同じで、`style="…"` 属性や nonce のない `<style>` は無視されます（`style_class()` を使ってください）。[Content Security Policy](#content-security-policyインラインスクリプト禁止)を参照してください。
- **GitHubのみ。** `repository_url` はGitHubリポジトリを指す必要があります——GitLab、自己ホストのGitサーバー、Git以外のアーカイブ元は使えません。

## 参照実装

[`kintai-bundle-notebook`](https://github.com/AudricSan/kintai-bundle-notebook) は、上のすべての小さく完結した例です：独自のモデル、リポジトリ、マイグレーション、宣言された権限、店舗の機能、ダッシュボードのウィジェット、通知の種類、本物のCoreに対して実行されるテスト。以前のパイロットである `kintai-bundle-feedback` は廃止されました（Coreはもう読み込みません）。
