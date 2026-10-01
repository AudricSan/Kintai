# アーキテクチャ：Kintai

🌐 [English](../architecture.md) · [Français](architecture.fr.md) · **日本語**

## 🏗 全体設計
Kintaiはモジュール化されたMVC（Model-View-Controller）アーキテクチャを採用し、高い可搬性と分離性を実現しています。

### 1. 「オーケストレーテッド・シングルテナント」モデル
Kintaiは共有インフラ型のSaaSではありません。
- **データプレーン：** 各オーナー（テナント）は専用のアプリケーションインスタンスと専用データベースを持ちます。
- **コントロールプレーン：** 中央のオーケストレーション層（Kintai SaaS）が、これらインスタンスのライフサイクル（プロビジョニング、更新、バックアップ）を管理します。

### 2. コアコンポーネント
- **コアアプリケーション：** PSR-12とSOLID原則に基づく独自設計のPHP 8.3フレームワーク。
- **ルーター：** Web・API・Cronの各ルートを扱う独自の正規表現ベースルーター。
- **コンテナ：** サービス管理のための軽量な依存性注入（DI）コンテナ。
- **ミドルウェア：** 認証・国際化・セキュリティなど横断的関心事のためのパイプライン。

## 📊 データ層（Eloquent ORM）
Kintaiは唯一のORMとして **スタンドアロン版Eloquent**（`illuminate/database`）を使用しています。
- **リポジトリ** がEloquentモデルをラップし、ドメインの分離を維持します。コントローラーがモデルを直接扱うことはありません。
- **ドライバー：** SQLite（デフォルト）とMySQL。
- **マイグレーション：** PHPベースで統一（`database/migrations/php/`）— 生のSQLファイルはもう存在しません。
- 旧来の `PersistenceDriverInterface` とJsonDBは削除済みです。

## 🧩 モジュール型バンドル
常時有効なコアを超える機能は、モノレポにまだ残っているもの（`src/Bundles/*/`）か、独立して配布されているもの（後述の「配布されるバンドル」参照）のいずれかとしてバンドルで提供されます——Coreは今やShift・User・Storeと横断的インフラ（認証、i18n、通知、設定、cronなど）にまで絞り込まれました。休暇申請、シフト交換、打刻、日報の4つはいずれも部分的な例外です：`TimeoffRequestRepositoryInterface`、`ShiftSwapRequestRepositoryInterface`、`TimeclockRepositoryInterface`、`DailyReportRepositoryInterface` はいずれもCoreサービスのままです（店舗統計、シフト計画サービス、管理者シフトコントローラー、iCalエクスポート、ホームコントローラー、従業員ダッシュボード、`DailyReportNavMiddleware`、自動検証cronジョブなど複数のCoreコンポーネントが、常に機能し続けるべき計算のためにこれらのデータを参照するため）。そのためこれら4つのバンドルのいずれかを無効化・アンインストールしても、それぞれの管理UIは失われますが、基となるデータやこれらのCoreの計算は失われません。日報は他の3つよりさらに踏み込んでいます：その権限/PDF/メール/自動検証サービス（`DailyReportPermissionService`/`PdfService`/`MailService`/`AutoValidateService`）もCore側（`AppServiceProvider`）で結び付けられています。これはリポジトリだけではありません——このバンドルをモノレポから抽出した際に、認証済みの全ページが壊れるという痛い経験から発覚しました（`DailyReportNavMiddleware`はグローバルミドルウェアであり、バンドルによる制御を一切受けません）。既存の店舗単位の`timeclock`機能トグルも、`messages`/`daily_reports`/`photos`と同様に、`AdminStoreController::FEATURE_BUNDLE_MAP`経由でこのバンドルによってフィルタリングされます。オープンシフトは異なります：そのデータを直接参照するCoreコンポーネントは存在しないため、`ShiftClaimRepositoryInterface` はCoreサービスとして残すのではなくバンドル自体に移動しました — 無効化するとデータごと機能全体が失われます。この抽出にあたり、`AdminShiftController`（Core）と `EmployeeController`（Core）からオープンシフトの公開・応募関連の6つのメソッドを分離する必要がありました。これらは以前、常時有効なシフト計画コントローラーにオープンシフトのロジックが混在していました。採用・退職・給与はかつて単一の `AdminReportController` を共有し、内部の `repo(string $type)` ディスパッチで処理されていました。このコントローラーは今や完全に姿を消し、`HasStaffReportCrud` トレイトを介してCRUD/PDFロジックを共有する3つのバンドルコントローラー（`AdminResignationReportController`、`AdminSalaryReportController`、`AdminHiringReportController`）に置き換わりました。給与の `calculateSalaryPreset()` メソッドは、当月の売上合計を事前入力するために `DailyReportRepositoryInterface`（Core側で結び付け、上記参照）を読み取ります。退職・給与とは異なり、`HiringReportRepositoryInterface` はバンドルへ移動せずCoreサービスのまま残ります——`AdminUserController` が従業員作成のたびに（通常フォームおよびExcelクイック作成）採用報告書を自動生成するため直接読み書きしており、これはhiring-reportバンドルが無効でも動作し続けるべきユーザー管理コアの仕組みです。無効化しても、閲覧・編集・PDFのUIのみが失われ、自動生成やデータ自体は影響を受けません。Feedbackは（Store Photosと同様に）完全な抽出です——他のCoreコンポーネントはフィードバックデータを一切読み取らないため、`FeedbackRepositoryInterface`はバンドル自身が登録します。一つ注意点があります：フィードバック送信モーダルは、共有レイアウト`app.php`によって全ての従業員向けページで直接インクルードされており（バンドル自身のビュー経由ではありません）、そのため`AuthMiddleware`が`feedback_enabled`フラグを共有し、レイアウトがインクルード前にそれを確認します——そうしないと、バンドルが利用不可になった後もモーダルが表示され続け、POST先が404になってしまいます。

ビュー側の全てのバンドルゲート（`src/Core/helpers.php`の`bundle_enabled()`/`feat_bundle()`。フィードバックモーダルだけでなく、nav・サイドバー・トップバー・ボトムナブ全体で使われています）は`BundleManager::isActive()`——boot後に実際に登録されたバンドル——を読み取り、インスタンスごとに保存された設定を反映するだけの`FeatureManager::isEnabled()`単体には決して頼りません。この2つは、バンドルが既存の設定で有効化されているのにディスク上にもう存在しない場合に食い違うことがあります：バンドルがモノレポから抽出される瞬間（Feedback、続いて日報）に、あるインスタンスの`app_settings.enabled_bundles`がまだそのバンドルを列挙していると、まさにこれが起こります——そのバンドルのページへの`route_url()`を無条件に組み立てていたnavのpartialは、`bundle_enabled()`が古いフラグに対して「fail-open」した瞬間に本番環境を壊しました。`AuthMiddleware`と`bundle_enabled()`はどちらもこれを痛い目に遭いながら経験しています。今後どのバンドルを抽出しても、この修正の恩恵を無料で受けられます。
- **自動検出：** `BundleDiscoveryService` は起動時に2つのソースを統合します——レガシーなモノレポのスキャン（`src/Bundles/*/`、`Bundle` を継承する `{名前}\{名前}Bundle` クラス、従来通りの同じPSR-4規約）と、動的にインストールされたバンドル（`storage/bundles/{slug}/{version}/`、各自の `bundle.json` から解決、詳細は後述の「配布されるバンドル」を参照）です。どちらのソースについてもレジストリへのハードコードは一切不要です。両方で検出されたスラッグ（通常は起こらないはずです）はレガシー側が優先され、警告がログに記録されます。
- **安定した契約：** `kintai\Core\BundleContract\Bundle` は、バンドル作成者が継承すべき、独立してバージョン管理される凍結済みの基底クラスです — ここでの破壊的変更はCHANGELOGで告知される正式なイベントであり、予告なく変更されうる他の `src/Core/` とは異なります。従来の場所である `kintai\Core\Bundle` は非推奨の互換エイリアスでしたが、モノレポ内のバンドルがすべて `kintai\Core\BundleContract\Bundle` を直接継承するよう移行し終えたため、V1.0を前に削除されました — まだ旧エイリアスを継承しているサードパーティ製バンドルは更新が必要です。
- **配布されるバンドル：** 上記のモノレポ方式に加えて、バンドルは今や独自のGitリポジトリに置かれ、独立してバージョン管理され、Kintai自体のコードに一切触れることなく `/admin/bundles/market` からインストールできるようになりました——Home Assistantのアドオンリポジトリと同じ考え方です。Ownerは1つ以上の**レジストリ**（`/admin/bundles/registries` — 静的な `registry.json` リスティングへのURLで、`BundleRegistryClient` が単純なHTTPで取得し、決してクローンしません。公式レジストリはデフォルトで追加されており削除できません）を追加し、リストされたバンドルを `BundleInstallerService` 経由でインストール・更新します。これはタグ付けされたGitHub Releaseのzipballをダウンロードし（`HttpFetcher`、`GithubUpdateService` と同じcurl→PHPストリームラッパーのフォールバック — 共有ホスティング上のPHPで`git`が利用できるとは限らないため、`git clone`/`pull`は決して使いません）、何かを書き込む前に `bundle.json` を検証し（スラッグの一致、Coreバージョンの互換性、エントリークラスの実在確認）、`storage/bundles/{slug}/{version}/` に有効化します——`src/Bundles/`の外側であり、このGitリポジトリには一切触れません。ドライランモードでは、何もインストールせずに全ての検証だけを実行できます。バンドルはこの方法で一つずつ `src/Bundles/` から移行していきます——それぞれ独自のリポジトリ（`kintai-bundle-{slug}`）に移り、公式レジストリ経由で配布されます。あるバンドルが既に移行済みかどうかは、`src/Bundles/` 配下にまだフォルダが残っているかどうかで判断できます。この方法でバンドルを書く人（社内・サードパーティ問わず）向けの完全ガイドは `docs/creating-a-bundle.md`（英語が原文、日本語訳あり）を参照してください。
- **フィーチャーフラグ：** 検出されたバンドルを実際に読み込むかどうかは `LicenseServiceProvider` から供給される `FeatureManager` が決定します — これは従来、このインスタンスにどのバンドルが存在するかというデプロイ上の設定でしたが、今ではフリーミアムのバンドル数の上限を適用する場所でもあります（後述の「ライセンス & フリーミアム」を参照）。オーナーは `/admin/bundles` からインスタンス単位で各バンドルを有効・無効化でき（`app_settings.enabled_bundles` に保存）、未設定の場合は `config/license.php` の `enabled_features` にフォールバックします。有効化されたバンドル内では、各店舗が自身の設定（店舗編集ページ）でさらに個別に利用有無を選択できます。
- **公式かサードパーティか：** `config/official-bundles.php` にはKintaiプロジェクトが実際に開発・保守しているバンドルのスラッグが一覧されています。この区別における唯一の真実の情報源であり、バンドル自身が「公式」と自己申告することはできません。`/admin/bundles` は検出されたがこの一覧にないバンドルをサードパーティとしてフラグ付けし、プロジェクト本体が保守していない旨の警告を表示します。
- **フックシステム：** Coreはバンドルに対し、UI要素・APIルート・ロジックを注入できる拡張ポイントを提供します。

## 💳 ライセンス & フリーミアム
セルフホスティングは、個人や小規模な組織での利用であれば引き続き無料かつオープンソースです。フリーミアムモデルはローカルで適用され、無料プランではネットワーク呼び出しは一切不要です。`PlanLimitService`（`src/Core/Services/`）は現在のプランの上限を決める唯一の情報源です——現時点では、このクラス内にハードコードされた無料プランで、アクティブな店舗は1つ、アクティブな従業員は15人、同時にアクティブなバンドルは4つ（Ownerが選択でき、`/admin/bundles` からいつでも入れ替え可能——1つ無効化すると別のバンドル用の枠が空きます）です。`StoreService::createStore()` と従業員作成の入口（`AdminUserController::storeUser()`/`quickCreateUser()`、`Api\V1\UserController::store()`）は `PlanLimitService::assertCanCreate*()` を呼び出し、これが `PlanLimitExceededException`（HTTP 403、他の `HttpException` と同様に描画）をスローします。`BundleSettingsController::save()` は代わりに、送信された件数に対して `PlanLimitService::maxActiveBundles()` を直接チェックします。`Api\V1\StoreController::store()` は（以前のようにリポジトリを直接呼ぶのではなく）`StoreService::createStore()` に委譲します。これは、この制限チェック——および既存の `StoreValidator` による検証——がWeb管理UIだけでなくAPIにも適用されるようにするためです。同等の `UserService` はまだ存在しないため、従業員側のチェックは一元化されておらず、3つの入口それぞれに重複しています。

有料ライセンスは上記の無料プランの上限を引き上げますが、必ずしも「無制限」になるわけではありません——各ライセンスが独自の数値上限（`max_stores`/`max_employees`/`max_bundles`。同じLicense Managerの上に構築された他のフリーミアム製品はまったく異なる指標を持つため、製品ごとに必要なキーを使える自由形式のJSONマップ）を持ち、名前付きのプランティア（Starter/Pro/Business、製品ごとに管理者が定義。作成時の便宜にすぎず、後からティアを編集しても、そこから発行済みのライセンスには一切影響しません）から設定するか、特別なライセンスのために個別に入力します。`PlanLimitService::maxStores()`/`maxEmployees()`/`maxActiveBundles()` は、`isPaidPlanActive()` が `true` のとき `LicenseClientService::entitlements()` からこれらを読み取ります。マップにないキー（または明示的な `null`）は、その指標に限って無制限を意味します。また、まだ検証可能な権利情報がない有料ライセンス（後述）は、すべての指標で無制限がデフォルトです——ティア導入前の挙動であり、すでに支払い済みのインスタンスが次回の同期を待つ間に制限されることがないようにするためです。

ライセンス管理自体は別プロジェクト（「License Manager」、Kintaiと同じアーキテクチャ：フロントコントローラー、Eloquent、PHPマイグレーション）であり、Kintaiだけでなくすべてのフリーミアム製品で再利用されます。その汎用API（`POST /activate`、`/validate`、`/deactivate`。製品は `api_key`、インスタンスは `license_key` + `instance_id` で識別）は、プレーンなフィールド（`valid`/`status`/`type`/`expires_at`/…。ログやLicense Manager自身のダッシュボードに便利）と、署名付きの `license_token` の両方を返します：`base64url(payload).base64url(signature)`、Ed25519（PHPの `openssl` 拡張、`algo=0` ——一部のXAMPP環境では利用できない `sodium` 拡張ではありません）で、ペイロードにはライセンスの種別/ティア/上限/有効期限/シート数が含まれます。これは偶然ではなく意図的なものです：セルフホストされたインスタンスのOwnerはデータベースを完全に制御できるため、`app_settings` にキャッシュされた単なる「有料かどうか」のフラグや上限の数値は、手作業で書き換えるだけで有料プランを解放できてしまいます。署名がこの穴を塞ぎます——`LicenseTokenVerifier`（`src/Core/Services/`）はローカルに設定された公開鍵（`LICENSE_SERVER_PUBLIC_KEY_B64`。検証にしか使えず偽造はできないため、同梱しても安全です——対応する秘密鍵はLicense Managerサーバーから出ることはなく、その `scripts/generate-signing-key.php` で生成されます）に対して署名を検証し、`PlanLimitService` は検証に成功したトークン由来の上限だけを信頼します。トークンが証明するのは発行された内容の*真正性*であって、*現在の*有効性ではありません——ライセンスは後からサーバー側で失効されうるため、それを検知するには既存の定期的な再検証（後述）が引き続き必要です。トークンはその代わりにはならず、両者は組み合わせて使われます（上限を読み取るたびにローカルで検証し、定期的にサーバーと照合する）。

`LicenseClientService`（`src/Core/Services/`）がこれらすべてを担います：Ownerが `/admin/license` で `license_key` を入力するまでは、ネットワークリクエストを一切行いません。`HttpFetcher::post()`（`BundleInstallerService`/`GithubUpdateService` と同じcurl/ストリームへのフォールバックの仕組み）を使い、`config/license_server.php` / `LICENSE_SERVER_URL`+`LICENSE_SERVER_API_KEY`+`LICENSE_SERVER_PUBLIC_KEY_B64` で設定します。サーバーとのやり取りの結果は毎回 `app_settings`（`license_key`、`license_state` —— ステータス/種別/有効期限/最後に有効だった時刻/最後に確認した時刻/`license_token`）にキャッシュされるため、`isPaidPlanActive()` と `entitlements()` がネットワークに到達する必要は一切ありません。`degraded` ステータス（直近の試行でサーバーに到達できなかった場合）は、最後に有効と確認されてから `grace_period_days`（デフォルト14日）の間は有料プランを維持し、その後は無料プランに戻ります——サーバーからの明示的な `valid:false`（失効/期限切れ/未検出）は、猶予なしで直ちに無料プランに戻ります。再検証は、Ownerが `/admin/license` を開いたときに随時行われるほか、`license-check` cronジョブ（`LicenseCheckJob`。`auto-validate`/`backup`/`log-purge` と同様に `CronRunner` に登録され、トークンは `scripts/create-cron-token.php --job=license-check` で発行）によって無人でも行われます。

1回の購入で複数の製品を同時にカバーすることもできます（License Managerの「ライセンスバンドル」：1人の顧客＋共通の種別/有効期限で、選択した製品ごとに1つのライセンスが生成され、それぞれが独自のキー/ティア/上限を持ち、License Manager管理者自身の管理と一括操作のためだけにグループ化されます——Kintaiのクライアントはバンドルという概念を持たず、他のキーと同様に自身のキーをアクティベートするだけです）。

## 🌐 マルチテナンシー
マルチテナンシーは**コードレベルではなくデプロイレベル**で実現されています。
- **分離：** オーナーごとの物理的分離。
- **店舗横断レポート：** 同一オーナーの全店舗が同じデータベースインスタンスを共有するため、ネイティブに処理されます。

## 🖥 フロントエンド戦略
- **サーバーサイドレンダリング（SSR）：** 速度・シンプルさ・デプロイのしやすさのためにネイティブPHPビューを使用。
- **Vanilla JS & CSS：** 重いビルド工程やフロントエンドフレームワークを使わず、アプリケーションを軽量かつカスタマイズしやすく保ちます。
- **モバイルファースト：** 外出先でシフトを確認する従業員向けのレスポンシブデザイン。

## 🔗 読みやすいURL
Webルートは店舗と従業員をデータベースIDではなく読みやすいセグメントで指定します：`/admin/stores/所沢東町店/edit`、`/admin/users/057/edit`。
- **型付きルートパラメーター：** パターンで `{id:store}` や `{uid:employee}` を宣言します。`Router::url()`/`route_url()` は渡されたIDをセグメント（パーセントエンコード）に変換し、`Application::dispatch()` はルートミドルウェアの**前に**受け取ったセグメントをIDへ戻すため、コントローラーと `PermissionMiddleware` は引き続き `$request->param()` で数値IDを読み取ります。バインダーは `src/Core/Routing/`（`StoreRouteBinder`、`EmployeeRouteBinder`、`RouteBinderRegistry` に登録）にあります。
- **店舗：** オーナーが入力する任意のスラッグ（ローマ字、例：`tokorozawa-higashicho`）、なければ店舗名そのまま。自動翻字は一切しません（ICUは漢字を中国語読みにしてしまうため）。`route_slugs` に保存され、以前のエイリアスもすべて保持されます。
- **従業員：** 従業員番号を使い、氏名は使いません（サーバーログや `Referer` に残る個人情報のため）。番号がない場合は `id-42`。以前の番号は、どの経路で変更されても `route_slugs` に保持されます（`DatabaseUserRepository::save()`）。
- **古いリンク：** 数値ID、以前のエイリアス、大文字小文字の違いは解決され、GETは正規URLへ `301` リダイレクトされます（クエリ文字列は保持）。POSTはそのまま処理されます。リダイレクトと未知セグメントの404はルートミドルウェアの**後で**のみ返されるため、権限のない訪問者は店舗名も従業員番号の存在も知ることができません。
- **予約語：** 登録済みルートのすべての固定セグメント（`create`、`export`、`stats` など、バンドルを含む）は、エイリアスとして使われると接尾辞が付きます。エイリアスが固定ページを隠すことはありません。
- **対象外：** `/api/v1/*`（機械向けの契約、IDのみ）、iCalフィードのURL、クエリ文字列のパラメーター。

## 📡 API・連携
- **API V1：** サードパーティツールとの連携を可能にするRESTful API。
- **iCal：** トークンで保護された、従業員向けの個人カレンダー連携。

## ⏱ 運用・CLIスクリプト
`scripts/db-migrate.php`（[データベース戦略](database.ja.md)参照）と `scripts/check-translations.php`（[コントリビュート](CONTRIBUTING.ja.md)参照）に加えて、定期的なメンテナンス作業を担う独立したCLIスクリプトがいくつか存在します——いずれも `Application` 全体を起動し、非対話的な実行（cron、スケジュールタスク、または手動実行）を前提としています：
- **`photo-retention.php`** — 設定可能な保持期間（`photo_retention_days`／`photo_cleanup_delay` 設定）を過ぎた店舗写真アップロードを削除します。
- **`consolidate-daily-photo-reports.php`** — 同日・同一店舗の写真投稿を遡って1件のレポートに統合します（`--dry-run` 対応）。詳細は `StorePhotoConsolidationService` を参照。
- **`auto-validate-reports.php`** — 店舗の締め切り時刻を過ぎても未承認のままの日報を自動承認します。外部スケジューラー向けにトークン保護されたHTTPエンドポイント（`/cron/auto-validate`）としても公開されています。
- **`create-cron-token.php`** — 汎用cronランナー（`/cron/run/{job}`）用のトークンを発行します。
- **`seed-demo-data.php`** — ローカルテスト用に、全バンドルを横断したリアルなサンプルデータを生成します（再シードは `--force`）。
