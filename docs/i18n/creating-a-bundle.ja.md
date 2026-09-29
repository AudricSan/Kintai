# バンドルを作成する

🌐 [English](../creating-a-bundle.md) · [Français](creating-a-bundle.fr.md) · **日本語**

このドキュメントは、**独立して配布される**Kintaiバンドルを作成する人向けです——独自のGitリポジトリ、独自のバージョン履歴を持ち、Kintai自体のコードベースに一切触れることなく `/admin/bundles/market` からインストールできるものです。従来の、今も使える、バンドルを本体リポジトリの `src/Bundles/` に直接置くパターンはここでは扱いません（それについては `docs/architecture.md` の「Modular Bundles」を参照してください）——この方法は今日Kintaiに同梱されている11個のバンドルに対しては今も有効ですが、新しいバンドル、特にサードパーティ製のものは、ここで説明する方式を使うべきです。

`kintai-bundle-feedback`（「Feedback」バンドル。この仕組み全体を検証するためのパイロットとしてKintai自体のモノレポから抽出されたもの）は、完全で実在する例です——読みながら別タブで開いておく価値があります。

## なぜ別リポジトリなのか、なぜ`git clone`を使わないのか

Kintaiはバンドルを取得するために `git clone`/`git pull` を実行することは決してありません——インストーラー（`BundleInstallerService`）は、単純なHTTP経由で**タグ付けされたGitHub Releaseのzipball**をダウンロードします（curl → PHPストリームラッパーへのフォールバック付き、`HttpFetcher` 参照）。これはKintaiが自身を更新するために既に使っているのと全く同じ仕組みです（`GithubUpdateService`）。これは意図的なものです：Kintaiが対象とするような多くの共有ホスティングでは、PHPから`git`バイナリに到達できる保証がありませんが、外向きのHTTPSリクエストは常に機能します。

これが以下すべての前提を形作ります：
- あなたのバンドルには**少なくとも1つの`vX.Y.Z`タグ付きGitHub Release**が必要です——単なるタグでは不十分で、`zipball_url`は実際のReleaseにしか存在しません。
- zipballの唯一のルートディレクトリがバンドルのインストール先ルートになります——GitHubがそれをどう命名するか（`{owner}-{repo}-{sha}`）は関係なく、中身だけが重要です。
- リリースアセットとして構築・アップロードする必要のあるものは何もありません：GitHubはタグ付けされたコミットから自動的に`zipball_url`を計算します。

## 必須のディレクトリ構成

```
your-bundle/
  bundle.json                 # マニフェスト — 下記参照、必須
  src/
    YourBundle.php            # エントリークラス。kintai\Core\BundleContract\Bundle を継承
    Controllers/Web/...
    Controllers/Api/...
  Views/                      # 任意 — loadViewsFrom() で読み込まれる
  lang/{en,fr,ja}.json         # 任意 — バンドル固有の翻訳キー
  routes.php                  # 任意 — loadRoutesFrom() で読み込まれる
  README.md
  LICENSE
```

ここでよくある2つの落とし穴：
- **`src/`配下に置くのはエントリークラス（および同じnamespace配下のもの）だけです。** `routes.php`、`Views/`、`lang/`はバンドルのルートに、`src/`の兄弟として置きます——中には入れません。これは`bundle.json`の`namespace`フィールドが`src/`専用のPSR-4ルートであることを反映しています。
- `Bundle::getPath()`はバンドルの**ルート**（`bundle.json`を含むディレクトリ）を返します。クラスファイルが2つのディレクトリのどちらにあっても関係ありません——上記の構成に従っている限り、`loadRoutesFrom($this->getPath() . '/routes.php')` と `loadViewsFrom($this->getPath() . '/Views', 'your-namespace')` はどちらも正しく解決されます。

## 安定した契約：`kintai\Core\BundleContract\Bundle`

あなたのエントリークラスはこの抽象クラスを継承します——`kintai\Core\*` の中で唯一、それ自体のセマンティックバージョニングに従い、変更時にCHANGELOGで告知される部分です（任意のメソッドを追加するのはマイナー変更、シグネチャの変更や削除は文書化されたメジャー変更です）。`kintai\Core\` 配下の他の部分にはこの保証はありません。

```php
abstract class Bundle
{
    abstract public function getName(): string;      // スラッグ — bundle.jsonの "slug" と一致する必要あり
    abstract public function register(): void;        // サービス・ルート・ビューを配線

    public function getVersion(): string;              // bundle.jsonの "version" と一致する必要あり
    public function getLabel(): string;                 // /admin/bundles(/market) に表示
    public function getDescription(): string;
    public function boot(): void;                       // 全バンドルの登録後に一度だけ実行
    public function getPath(): string;                  // バンドルのルート、上記参照

    protected function loadRoutesFrom(string $path): void;
    protected function loadViewsFrom(string $path, string $namespace): void;
}
```

最小限の実例（`kintai-bundle-feedback`の実際のエントリークラスを簡略化したもの）：

```php
<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Feedback;

use kintai\Core\BundleContract\Bundle;
use kintai\Core\Repositories\FeedbackRepositoryInterface;
use kintai\Core\Repositories\DatabaseFeedbackRepository;

final class FeedbackBundle extends Bundle
{
    public function getName(): string { return 'feedback'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getLabel(): string { return __('bundle_feedback'); }
    public function getDescription(): string { return __('bundle_feedback_desc'); }

    public function register(): void
    {
        $this->app->container()->singleton(
            FeedbackRepositoryInterface::class,
            fn() => new DatabaseFeedbackRepository()
        );
        $this->loadViewsFrom($this->getPath() . '/Views', 'feedback');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }
}
```

namespaceは何でも構いません——慣例として、インストールされるバンドルは `kintai\Bundles\Installed\{名前}\` を使用します。これはレガシーなモノレポの慣例（`kintai\Bundles\{名前}\`）とは意図的に区別されており、2つのオートロード機構（`src/Bundles/`用のComposer自身のPSR-4と、`storage/bundles/`配下用の専用`spl_autoload_register`）が同じクラス名で衝突することは決してありません。

**`src/Core/Repositories/*Interface.php` のリポジトリ/インターフェースのペアも、依存してよい安定した領域の一部です**（上記の`FeedbackRepositoryInterface`など）——これらは`BundleContract`とは無関係に、構造上すでに安定した契約です。

## RBACスコープの契約：`managed_store_ids`

バンドルが `middleware: [AuthMiddleware::class, PermissionMiddleware::class]` の下に登録するすべてのWebルートは、`permission:` を宣言しなければなりません——`PermissionCatalog`の正確なキー、または`'public'`（意図的に細かいゲートをかけないルート。例：セルフサービス/集計ページ。`permission:`を省略しても同じ扱いになります）のいずれかです。`PermissionMiddleware`はこの規則を`managed_store_ids`というリクエスト属性に解決し、それ以降のすべてのコントローラは自分で再計算するのではなく、この属性を読むことが期待されています：

- **`null`** — 無制限。ユーザーがOwnerである場合、またはそのロールがこのルートの権限キーを「全店舗」スコープ（`role_permissions.scope = 'global'`）付きで付与している場合——これは、そのユーザーをそのロールに結びつけている*割り当て（assignment）*自体が本人の所属店舗にスコープされていても発生し得ます。どちらの場合も意味は同じです：店舗による絞り込みは一切なし。
- **`int[]`** — これらの店舗IDのみに制限。

**このバグを毎回再発させる、たった一つの間違い**：`=== null` で明示的に分岐する代わりに `$request->getAttribute('managed_store_ids') ?? []` と書いてしまうことです。Coreのリポジトリメソッドの多くが*空の*フィルタ配列を「フィルタなし」として扱うため、これは無害に見えます——下流のリスト取得は偶然「動作し続ける」ことがあります——しかし、同じ値を読む次のコード（店舗選択ドロップダウン、`in_array($storeId, $managedIds)`によるアクセスチェックなど）は「店舗ゼロ」を見てしまい、「全店舗」ではないため破綻します。この間違いはまさに`kintai-bundle-daily-report`の`DailyReportController::indexAll()`で発生していました（`fix/global-scope-permission-store-filter`で修正済み）——`null`は常に`is_admin`と同様、独立した分岐として扱い、`??`で吸収させてはいけません。

この解決ロジックを自分で再実装しないでください。Coreの`kintai\UI\Controller\Web\HasAdminAccess`トレイト——`managedIds(Request $request): ?array`、`availableStores(?array $managedIds): array`、`assertStoreAccess(Request $request, int $storeId): void`、`assertAnyStoreAccess(Request $request, array $storeIds): void`——を再利用してください。これは公式バンドルの各管理コントローラが使っているのと同じトレイトです（実例として`kintai-bundle-store-photos`の`StorePhotoController`を参照）。すでに`null`のケースを正しく処理しています。自分のコントローラで手作りした同等品を書くことは、まさにこの種のバグがバンドルごとに再導入される原因です。

バンドルが同じ認可の下で**アップロードされたファイル**（画像、PDF、添付ファイル）を配信する必要がある場合、そのための独自のファイル配信ルートを作らないでください。Kintai自身の`/storage/{path*}`（ルート名`storage.file`）にリンクしてください。これは`managed_store_ids`（`StorageFileController::assertPathStoreAccess()`）に加え、独自のアップロードパス制限とMIMEホワイトリストをすでに適用しています。並行するファイル配信ルートは、Coreが既に持っている認可ロジックを、その自身のテストカバレッジの外側で複製することになります。

## `bundle.json` マニフェスト

バンドルのリポジトリのルートに必須：

```json
{
    "slug": "feedback",
    "name": "Retours utilisateurs",
    "version": "1.0.0",
    "description": "Employee feedback (bugs, suggestions) — submission modal, admin list and deletion.",
    "author": { "name": "あなたの名前", "email": "you@example.com", "url": "https://example.com" },
    "kintai_core": { "min": "0.1.0", "max": "999.999.999" },
    "requires_bundles": {},
    "namespace": "kintai\\Bundles\\Installed\\Feedback",
    "entry_class": "kintai\\Bundles\\Installed\\Feedback\\FeedbackBundle",
    "license": "AGPL-3.0-only",
    "homepage": "https://github.com/you/your-bundle"
}
```

| フィールド | 必須 | 備考 |
|---|---|---|
| `slug` | はい | `getName()` の返り値と一致する必要があります——インストール時にチェックされ、一致しないスラッグはディスクに何も書き込まれる前に拒否されます。 |
| `name` | はい | 人間が読める名前。カタログに表示されます。 |
| `version` | はい | 厳密なsemver（`X.Y.Z`）、`version_compare()`で比較されます。`getVersion()`の返り値と一致する必要があります。 |
| `namespace` | はい | `src/`配下すべてのPSR-4ルート。 |
| `entry_class` | はい | エントリークラスの完全修飾名。`namespace`から解決した際に`src/`配下にそのファイルが存在する必要があります（有効化前にチェック）。 |
| `description`、`author`、`license`、`homepage` | いいえ | 情報提供のみ。 |
| `kintai_core.min`/`.max` | いいえ（デフォルト `0.0.0`/`999.999.999`） | インストーラーは、稼働中インスタンスのCoreバージョンがこの範囲外の場合、バンドルの有効化を拒否します。 |
| `requires_bundles` | いいえ（デフォルト `{}`） | スラッグ→バージョン制約。**現時点では情報提供のみ** — 「既知の制限」を参照。 |

## リリースを公開する

1. `bundle.json`の`version`を上げる（エントリークラスの`getVersion()`も——両者は一致している必要があります）。
2. コミットに`vX.Y.Z`のタグを付け、そのタグをプッシュする。
3. そのタグに対してGitHub Releaseを作成する（`gh release create vX.Y.Z --generate-notes`、またはタグのプッシュ時に同じことを行うCIワークフロー——最小限の例として`kintai-bundle-feedback`の`.github/workflows/release.yml`を参照）。何も構築する必要はありません：Releaseが自動生成する`zipball_url`こそが、`BundleInstallerService`がダウンロードするものそのものです。

## レジストリに掲載してもらう

**レジストリ**とは、単純なHTTPSで配信される静的な`registry.json`ファイルに過ぎません（GitHubリポジトリの`raw.githubusercontent.com` URLがうまく機能し、公式レジストリもこの方法で配信されています）——Kintaiはこれもクローンせず、単にGETするだけです（`BundleRegistryClient`）。

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

- `schema_version` — Kintaiは現在`1`と`2`を理解します。より新しいスキーマを理解できないインスタンスは、誤って解釈するのではなく、そのリスティングを（ログを記録した上で）きれいに拒否します。
- `repository_url`は単純な`https://github.com/{owner}/{repo}`である必要があります——インストーラーはここからGitHub APIのリリース検索URLを導出します。
- `versions`（schema 2）は更新チャンネルごとにキー分けされます——`release`（`main`から公開された非プレリリースのみ）、`beta`（`main`または`beta`、`alpha`を除く）、`alpha`（すべて）——それぞれ最新順のインストール可能なバージョンのリストです。これは`/admin/bundles/market`でインストール済みのすべてのバンドルに対して一度だけ選ぶチャンネル（`AppSettingsService::bundleUpdateChannel()`、`/admin/update`のCore自身のチャンネルとは独立）と対応しており、そのチャンネルに一致するリストがカタログUIで「最新」として提示され、通常の更新でインストールされる内容になります。**schema 1**（`versions`がチャンネルキーのないフラットな配列）も後方互換性のため引き続き受け付けられます——その場合Kintaiは三つのチャンネルすべてに同じフラットなリストを提示します。どのリリースがどのブランチから来たか判別する手段がないためです。公式レジストリはこのschema 2の3つのリストを、各バンドルの実際のGitHub Releases（`target_commitish`/`prerelease`、Kintai自身のリリースチャンネルと同じ規則）から自動的に算出します——[`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)の`scripts/sync-versions.js`を参照してください。これは1時間ごとに実行され、新しいリリースでいずれかのバンドルのバージョンリストが変わるたびにPRを開きます。`versions`を手動で編集することはありません。
- あなたのリポジトリ内の`bundle.json`は、互換性（`kintai_core.min`/`max`）やその他すべてに関する実際の情報源であり続け、インストール時に読み取られます——ここでの`versions`はカタログUIへの、何がインストール可能でどのチャンネルにあるかというヒントに過ぎません。

見つけてもらう方法は2つあります：
- **自分のレジストリ** — 自分で`registry.json`を書いてホストし（HTTPSでアクセスできればどこでも構いません）、そのURLを誰でも`/admin/bundles/registries`から追加できます。誰の承認も不要です。
- **Kintai公式レジストリ**（[`AudricSan/KintaiBundle`](https://github.com/AudricSan/KintaiBundle)）——その`registry.json`にエントリを追加するプルリクエストを開いてください。そこに掲載されても、あなたのバンドルが「公式」になる**わけではありません**——下記参照。

## 公式 vs. サードパーティ

Kintai自身のリポジトリ内にある`config/official-bundles.php`が、どのスラッグがKintaiプロジェクトによって保守されているかについての**唯一の**情報源です——どのレジストリに掲載されていようと、バンドルが自ら公式を名乗ることはできません。それ以外はすべてカタログUIで「サードパーティ」と表示され、インストールや更新には明示的な`confirm_third_party`チェックボックスが必要で、これはサーバー側で強制されます（JavaScriptで隠しているだけではありません）——ユーザーには警告が表示されることを想定し、続行前にそれを読んでもらう前提でバンドルを設計してください。

## 既知の制限（本稿執筆時点）

- **単一プロセス、単一のComposerオートローダー。** バンドルごとの`composer.json`や依存関係の分離はありません——あなたのバンドルはKintai自身の`kintai\`ルートと同じPHPプロセス、同じnamespaceツリーの中で動作します。依存してよいのは`BundleContract\Bundle`、`src/Core/Repositories/*Interface.php`のインターフェース、そしてKintaiのバージョン間で結合しても構わないと思える他のCoreクラスだけにしてください。
- **`requires_bundles`はまだ強制されません。** 他のバンドルのスラッグ／バージョンへの依存を宣言することは受け付けられ保存されますが、それが欠けていたり古すぎたりしてもインストールをブロックするものは現時点ではありません——今のところは保証ではなくドキュメントとして扱ってください。
- **アンインストールの仕組みはまだありません。** `/admin/bundles/market` はインストールと更新はできますが、バンドルのファイルとデータベースの行を削除する仕組みはまだ配線されていません。
- **GitHubのみ。** `repository_url`はGitHubリポジトリを指す必要があります——GitLabも、自前ホストのGitサーバーも、Git以外のアーカイブソースも使えません。

## リファレンス実装

[`kintai-bundle-feedback`](https://github.com/AudricSan/kintai-bundle-feedback) は、この仕組み全体を検証するために作られたパイロットバンドルです——上記すべての完全で動作する最小限の例であり、Kintai自身の`src/Bundles/Feedback/`から、動作を一切変えずに抽出されました。
