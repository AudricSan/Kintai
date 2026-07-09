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
機能は **Core**（常に有効）と **Bundles**（`src/Bundles/*/`）に分かれていますが、いずれもこのAGPL-3.0リポジトリの一部であり、別ライセンスの下に隠された機能はありません。常時有効なコアを超える機能はすべてバンドルとして提供される想定です。現在はMessaging、Daily Reports、Store Photos、休暇申請、シフト交換、オープンシフト（シフト応募）、退職報告書、給与報告書、採用報告書、Feedback、そして打刻があります——Coreは今やShift・User・Storeと横断的インフラ（認証、i18n、通知、設定、cronなど）にまで絞り込まれました。休暇申請、シフト交換、打刻の3つはいずれも部分的な例外です：`TimeoffRequestRepositoryInterface`、`ShiftSwapRequestRepositoryInterface`、`TimeclockRepositoryInterface` はいずれもCoreサービスのままです（店舗統計、シフト計画サービス、管理者シフトコントローラー、iCalエクスポート、ホームコントローラー、従業員ダッシュボードなど複数のCoreコンポーネントが、常に機能し続けるべき計算のためにこれらのデータを参照するため）。そのためこれら3つのバンドルのいずれかを無効化しても、それぞれの管理UIは失われますが、基となるデータやこれらのCoreの計算は失われません。既存の店舗単位の`timeclock`機能トグルは、`messages`/`daily_reports`/`photos`と同様に、`AdminStoreController::FEATURE_BUNDLE_MAP`経由でこのバンドルによってもフィルタリングされるようになりました。オープンシフトは異なります：そのデータを直接参照するCoreコンポーネントは存在しないため、`ShiftClaimRepositoryInterface` はCoreサービスとして残すのではなくバンドル自体に移動しました — 無効化するとデータごと機能全体が失われます。この抽出にあたり、`AdminShiftController`（Core）と `EmployeeController`（Core）からオープンシフトの公開・応募関連の6つのメソッドを分離する必要がありました。これらは以前、常時有効なシフト計画コントローラーにオープンシフトのロジックが混在していました。採用・退職・給与はかつて単一の `AdminReportController` を共有し、内部の `repo(string $type)` ディスパッチで処理されていました。このコントローラーは今や完全に姿を消し、`HasStaffReportCrud` トレイトを介してCRUD/PDFロジックを共有する3つのバンドルコントローラー（`AdminResignationReportController`、`AdminSalaryReportController`、`AdminHiringReportController`）に置き換わりました。給与の `calculateSalaryPreset()` メソッドは、当月の売上合計を事前入力するために `DailyReportRepositoryInterface`（DailyReportバンドル）を読み取ります——バンドル間依存であり、給与報告書の作成にはdaily-reportが有効なままであることが前提です。退職・給与とは異なり、`HiringReportRepositoryInterface` はバンドルへ移動せずCoreサービスのまま残ります——`AdminUserController` が従業員作成のたびに（通常フォームおよびExcelクイック作成）採用報告書を自動生成するため直接読み書きしており、これはhiring-reportバンドルが無効でも動作し続けるべきユーザー管理コアの仕組みです。無効化しても、閲覧・編集・PDFのUIのみが失われ、自動生成やデータ自体は影響を受けません。Feedbackは（Store Photosと同様に）完全な抽出です——他のCoreコンポーネントはフィードバックデータを一切読み取らないため、`FeedbackRepositoryInterface`はバンドル自身が登録します。一つ注意点があります：フィードバック送信モーダルは、共有レイアウト`app.php`によって全ての従業員向けページで直接インクルードされており（バンドル自身のビュー経由ではありません）、そのため`AuthMiddleware`が`FeatureManager`由来の`feedback_enabled`フラグを共有し、レイアウトがインクルード前にそれを確認します——そうしないと、バンドルが無効化された後もモーダルが表示され続け、POST先が404になってしまいます。
- **自動検出：** `BundleDiscoveryService` が起動時に `src/Bundles/*/` をスキャンし、`Bundle` を継承する `{名前}\{名前}Bundle` クラスを検出します — レジストリへのハードコードは一切不要です。サードパーティ製バンドルも `src/Bundles/` に配置するだけで（同じPSR-4規約：`kintai\Bundles\{名前}\{名前}Bundle`）検出され、core側のコード変更は不要です。
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
