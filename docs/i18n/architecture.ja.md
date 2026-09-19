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
- **安定した契約：** `kintai\Core\BundleContract\Bundle` は、バンドル作成者が継承すべき、独立してバージョン管理される凍結済みの基底クラスです — ここでの破壊的変更はCHANGELOGで告知される正式なイベントであり、予告なく変更されうる他の `src/Core/` とは異なります。従来の場所である `kintai\Core\Bundle` は、モノレポ内の11個のバンドルが移行するまで無変更で動き続けるように残された非推奨のエイリアスです。
- **配布されるバンドル：** 上記のモノレポ方式に加えて、バンドルは今や独自のGitリポジトリに置かれ、独立してバージョン管理され、Kintai自体のコードに一切触れることなく `/admin/bundles/market` からインストールできるようになりました——Home Assistantのアドオンリポジトリと同じ考え方です。Ownerは1つ以上の**レジストリ**（`/admin/bundles/registries` — 静的な `registry.json` リスティングへのURLで、`BundleRegistryClient` が単純なHTTPで取得し、決してクローンしません。公式レジストリはデフォルトで追加されており削除できません）を追加し、リストされたバンドルを `BundleInstallerService` 経由でインストール・更新します。これはタグ付けされたGitHub Releaseのzipballをダウンロードし（`HttpFetcher`、`GithubUpdateService` と同じcurl→PHPストリームラッパーのフォールバック — 共有ホスティング上のPHPで`git`が利用できるとは限らないため、`git clone`/`pull`は決して使いません）、何かを書き込む前に `bundle.json` を検証し（スラッグの一致、Coreバージョンの互換性、エントリークラスの実在確認）、`storage/bundles/{slug}/{version}/` に有効化します——`src/Bundles/`の外側であり、このGitリポジトリには一切触れません。ドライランモードでは、何もインストールせずに全ての検証だけを実行できます。バンドルはこの方法で一つずつ `src/Bundles/` から移行していきます——それぞれ独自のリポジトリ（`kintai-bundle-{slug}`）に移り、公式レジストリ経由で配布されます。あるバンドルが既に移行済みかどうかは、`src/Bundles/` 配下にまだフォルダが残っているかどうかで判断できます。この方法でバンドルを書く人（社内・サードパーティ問わず）向けの完全ガイドは `docs/creating-a-bundle.md`（英語が原文、日本語訳あり）を参照してください。
- **安定した契約：** `kintai\Core\BundleContract\Bundle` は、バンドル作成者が継承すべき、独立してバージョン管理される凍結済みの基底クラスです — ここでの破壊的変更はCHANGELOGで告知される正式なイベントであり、予告なく変更されうる他の `src/Core/` とは異なります。従来の場所である `kintai\Core\Bundle` は、モノレポ内の11個のバンドルが移行するまで無変更で動き続けるように残された非推奨のエイリアスです。これは、モノレポ限定のバンドルから、個別にバージョン管理・配布されるバンドル（マニフェスト＋レジストリ＋インストーラー、対応中）への移行の第一歩です。
- **フィーチャーフラグ：** 検出されたバンドルを実際に読み込むかどうかは `LicenseServiceProvider` から供給される `FeatureManager` が決定します — これはライセンスの問題ではなく、デプロイ上の設定です。オーナーは `/admin/bundles` からインスタンス単位で各バンドルを有効・無効化でき（`app_settings.enabled_bundles` に保存）、未設定の場合は `config/license.php` の `enabled_features` にフォールバックします。有効化されたバンドル内では、各店舗が自身の設定（店舗編集ページ）でさらに個別に利用有無を選択できます。
- **公式かサードパーティか：** `config/official-bundles.php` にはKintaiプロジェクトが実際に開発・保守しているバンドルのスラッグが一覧されています。この区別における唯一の真実の情報源であり、バンドル自身が「公式」と自己申告することはできません。`/admin/bundles` は検出されたがこの一覧にないバンドルをサードパーティとしてフラグ付けし、プロジェクト本体が保守していない旨の警告を表示します。
- **フックシステム：** Coreはバンドルに対し、UI要素・APIルート・ロジックを注入できる拡張ポイントを提供します。

## 🌐 マルチテナンシー
マルチテナンシーは**コードレベルではなくデプロイレベル**で実現されています。
- **分離：** オーナーごとの物理的分離。
- **店舗横断レポート：** 同一オーナーの全店舗が同じデータベースインスタンスを共有するため、ネイティブに処理されます。

## 🖥 フロントエンド戦略
- **サーバーサイドレンダリング（SSR）：** 速度・シンプルさ・デプロイのしやすさのためにネイティブPHPビューを使用。
- **Vanilla JS & CSS：** 重いビルド工程やフロントエンドフレームワークを使わず、アプリケーションを軽量かつカスタマイズしやすく保ちます。
- **モバイルファースト：** 外出先でシフトを確認する従業員向けのレスポンシブデザイン。

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
