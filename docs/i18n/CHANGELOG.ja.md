# Changelog（変更履歴）

🌐 [English](../../CHANGELOG.md) · [Français](CHANGELOG.fr.md) · **日本語**

Kintaiの主要な変更履歴をここに記録しています。

## [Unreleased]

## [0.6.0] - 2026-07-10

### 変更
- `/docs` の言語選択グリッドを廃止しました：wikiをアプリ内で直接閲覧できるようになった今、言語カードを選ぶ手順は付加価値のないステップになっていました。`GET /docs` は、言語ごとの「オンラインで見る」ボタンを表示する代わりに、ユーザーの現在のロケールにおけるwikiのホームページへ直接遷移するようになりました（`DocsController::index()` は、そのページが `.wiki/` に存在すればすぐに `docs.show` へリダイレクトします）。現在のロケールがローカルにキャッシュされていない場合、`/docs` はその旨を示すメッセージを表示し、「GitHubで見る」リンク（`.wiki/` のクローンが全く無くても機能します）と、オーナー向けにはその場で取得するための「wikiをクローン」ボタンを表示します。wiki閲覧ページ（`docs/show.php`）にも、オーナー限定の「wikiを更新」ボタンがヘッダーに追加されたため、コンテンツを更新するのにハブページへ戻る必要がなくなりました — 現在閲覧中のどのページからでも実行できます。
- 翻訳ドキュメント（`*.fr.md`、`*.ja.md`）をすべて `docs/i18n/` に移動しました。以前は英語版ソースと同じ `docs/` 内に置かれていたものも含みます。ルートの `SECURITY.md` の翻訳とのファイル名の大文字小文字非依存の衝突を避けるため、`docs/security.md` は `docs/security-overview.md` にリネームされました。`scripts/check-translations.php` は、ソースドキュメントのフォルダに関わらず、常に `docs/i18n/` 配下の翻訳を探すようになりました。
- `docs/database.md` を更新し、実際の `illuminate/database` のバージョン（^11.0ではなく^13.19）を反映するとともに、`Language`/`Translation` のリポジトリインターフェースが現在Eloquentではなく、JSONファイルベースの実装にバインドされていることを明記しました — 対応する `Database*Repository` は、i18nのJSON移行以前からの未使用のデッドコードです。
- 「バックアップ＆アップデート」の統合された設定タブを、**バックアップ**と**アップデート**の2つのタブに分割しました。GitHubによる自己アップデートUI（バージョン、保留中のマイグレーション、進捗表示付きワンクリックアップデート）は独自のページ `/admin/update` に移動し、`/admin/backup` は作成・復元・削除のみを扱います。内部の `POST /admin/backup/update(/stream)` および `/migrate` ルートは、`/admin/update/apply`、`/admin/update/stream`、`/admin/update/migrate` に移動しました。
- `UpdateService::getCurrentVersion()` は、`storage/app/version.json` の個別の `version` フィールドを追跡する代わりに、インストール済みバージョンを `config/app.php`（各リリースで更新され、自己アップデート処理により同期される）から直接読み取るようになりました。同ファイルは引き続き `installed_at`／`updated_at`／`duration_seconds` を記録しますが、アプリのバージョンの正とはなりません。
- `docs/vision.md` を改訂：ターゲット市場を、日本優先／世界の中小企業という区分ではなく、業種（小売業、飲食業、その他シフト制の複数拠点業種）で捉え直しました。最後のスローガンも更新しました。
- `docs/business-model.md` を改訂：料金プランの箇条書きを、Community／Pro／Business／Enterpriseの表に変更し、有料プランはマネージドSaaS上でデフォルトで何を有効化・サポートするかを決めるものであり、オープンソースのコードベースに存在する内容を制限するものではないと明記しました。直接営業のメッセージングも、もはや日本に絞ったものではありません。
- `docs/architecture.md`：Webhookの「（計画中）」の行を削除しました（もはやロードマップ上にありません）。人事レポート（採用・退職・給与）と店舗写真は、現在はバンドルではなくCore内に存在しており、将来的にバンドルへ移行する候補であることを明記しました。
- `docs/security-overview.md`：新しい業種ベースの位置付けに合わせ、「日本の労働法」を「各国の労働法」に一般化しました。
- `docs/i18n/database.fr.md` と `docs/i18n/database.ja.md` を英語版ソースと同期させました（Eloquentのバージョン、`Language`/`Translation` リポジトリのデッドコードに関する注記）。

### 追加
- オーナーは、サーバー上で手動で `git clone` する代わりに、`/docs` から直接ローカルwiki（`.wiki/`）をクローンまたは更新できるようになりました：新しい `WikiSyncService` が、wikiの `_Sidebar.md` に列挙されている全ページ（3言語分）を `raw.githubusercontent.com/wiki/{repo}/...` から単純なHTTPS経由で取得します — `git` バイナリや `exec()` は不要で、`GithubUpdateService` の自己アップデート機構と同じ、共有ホスティングに適した方式です。冪等な `sync()` を1回呼び出すだけで両方のケースに対応します：初回実行時（ローカルに何もない状態）はすべてをクローンし、以降の実行では実際に内容が変わったページのみを再書き込みし、変更のないページはスキップし、翻訳が存在しない場合（HTTP 404）はエラーではなく正常なケースとして扱います。`POST /docs/sync`（`DocsController::sync()`、オーナー限定、`BackupController`／`LanguageController` と同じ `requireOwner()` パターンに準拠）として公開され、`/docs` にはオーナー向けに「wikiをクローン」／「wikiを更新」ボタンが表示され、追加／更新／変更なしの件数が報告されます。`WikiSyncService` のコンストラクタはnullableな `\Closure`（テスト専用のフェッチャー差し替え）を受け取るため、コンテナのリフレクションによる自動解決だけではインスタンス化できず、`AppServiceProvider` に明示的なシングルトンバインディングが必要でした — `GithubUpdateService` が既に同様のバインディングを必要としていたのと同じ理由です。
- ドキュメントページ（`/docs`）が、GitHub wikiをアプリ内に直接表示するようになりました：新しいルート `GET /docs/{lang}/{page}`（`DocsController::show()`）が、新しい `WikiContentService` を介して各wikiページをHTMLとして描画します。このサービスはローカルの `.wiki/` クローン（gitでは無視され、実際のGitHub wikiリポジトリとは別に配置される）を読み込みます。構造は、固定のファイル一覧を仮定するのではなく、wiki が実際に採用している構成 — 英語ページはルートに、`fr/`／`ja/` サブフォルダに翻訳を配置し、ナビゲーションはwiki自身の `_Sidebar.md` を解析して導出 — に従っています。閲覧用ビューは、サイドバーの目次をサイドバーのセクションごとにグループ化し、前へ／次へのページ送りと言語切り替え（現在のページに翻訳がある場合に表示）を追加し、各ページのタイトルは最初の `#` 見出しから取得します。wiki内部のリンク（GitHubの相対パス方式、例：`Installation`、`../Home`、`../ja/Home`）は解決されアプリ内ルートに書き換えられます — ディレクトリトラバーサル対策としてslugの検証も行います —、外部リンクはそのまま維持され、解決できない内部リンクはプレーンテキストに縮退します。`/docs` の各言語カードは、そのページが実際にディスク上に存在する場合、wikiのホームページへ直接遷移します。また、`.wiki/` がローカルにクローンされているかどうかにかかわらず、常にオンラインwikiへの「GitHubで見る」リンクを表示します — 閲覧用ページでも同様です（現在のページ用のヘッダーリンク、および現在の言語で利用できないページ用のサイドバー内の項目別リンク）。GitHubのURLは、自己アップデートの確認に既に使われている `GITHUB_UPDATE_REPO` 環境変数から構築されるため（`WikiContentService::githubWikiUrl()`）、ファイルシステムへのアクセスを必要としません。これにより、従来のPDFベースのガイドシステム（`WikiPdfGeneratorService`、`WikiPdfController`、`scripts/pdf.php`、コミットされていた `public/pdf/*.pdf` ファイル、`/api/v1/pdf/generate` エンドポイント）は完全に削除されました。
- 孤立していた `backup` cronジョブを接続しました：`CronRunner`、`AutoValidateJob`、`BackupJob` は完全に実装されていましたが、登録もされておらず、どのルートからも到達できませんでした。`AppServiceProvider` は、両方のジョブを登録した `CronRunner` シングルトンを構築するようになり、新しい `GET /cron/run/{job}` ルート（`cron_tokens` テーブルによるジョブごとのトークン保護、`Authorization: Bearer` または `?token=`）がこれらを公開します。`scripts/create-cron-token.php` が、このエンドポイントに必要なトークンを発行します。

### 修正
- `storage/app/database.sqlite` が存在しない場合（例：以前のインストールが不完全だった、または削除された場合）に、インストーラーが「Database file at path [...] does not exist.」で失敗しなくなりました — フレームワーク起動前に、ファイルとそのディレクトリが自動的に作成されるようになりました。
- `docs/releasing.md`（およびそのFR/JA翻訳）が、自己アップデートUIについて依然として `/admin/backup` を指していた問題を修正し、設定タブの分割に合わせて `/admin/update` を指すようにしました。
- **セキュリティ：** MessagingバンドルとDailyReportバンドルの `/api/v1/*` ルートは、他のすべての `/api/v1/*` エンドポイントとは異なり `ApiAuthMiddleware` なしで登録されていました — コントローラーは認証済みの `auth_user` を前提としていたにもかかわらず、Bearerトークンなしでアクセス可能な状態でした。両バンドルの `routes.php` は、CoreのAPIと同じ保護されたグループ内でAPIルートをラップするようになりました。
- **重大：** シフト交換機能が完全に壊れていました — 交換の作成・承諾・拒否が、`shift_swap_requests` テーブルに存在しないカラム `target_id`／`peer_accepted_at`（実際のカラムは `target_user_id`／`accepted_at`）を読み書きしていました。交換を作成しようとするたびにSQLエラーが発生し、交換の統計はターゲット側の件数を静かに過小評価していました。`EmployeeController`、`AdminSwapController`、交換関連のビュー、ダッシュボード、`StoreStatsService` で修正しました。
- `DELETE /api/v1/notifications/{id}` が、通知を削除する代わりに既読にするだけでした。`NotificationRepositoryInterface` に `delete()` メソッドを追加し、このエンドポイントは実際に削除するようになりました。
- `scripts/db-migrate.php --dry-run` は文書化されていましたが実装されていませんでした — スクリプトはCLI引数を一切読み取っていませんでした。現在は、適用せずに保留中のマイグレーションを一覧表示します。
- `scripts/docker-setup.php` は、`install.php`／`provision.php` とは異なるbcryptコストで管理者パスワードをハッシュ化していました。コスト12に統一しました。
- `.env.example` に、コードの他の箇所で `env()` 経由で実際に読み取られている複数の変数（`DB_PREFIX`、`DB_CHARSET`、`DB_COLLATION`、`DB_DRIVER`、`SESSION_NAME`、`SESSION_LIFETIME`、`SESSION_SECURE`、`SESSION_HTTPONLY`、`SESSION_SAMESITE`、`APP_NAME`、`APP_VERSION`）が記載されていませんでした。実際のデフォルト値とともに追加しました。

## [0.0.3-beta] - 2026-07-08

### 追加
- **初回パブリックベータ公開。** 充実したシフト管理機能（リスト・カレンダー・週・日・ガントチャート形式のタイムライン表示、ドラッグ＆ドロップ、一括操作、印刷、重複コンフリクトの検出、オープンシフトとシフト応募制度、視覚解析と信頼度スコアリングによるExcelインポート）。勤怠管理（リアルタイムタイマー付き打刻、管理者による編集、給与自動概算）。従業員向けセルフサービス（休暇申請、シフト交換、週次の勤務可能時間登録、iCal連携、フィードバックウィジェット）。リアルタイム更新（SSE）対応のスレッド形式社内メッセージングと通知センター。下書き→提出→承認のワークフロー、PDF出力（CJK対応）、自動承認スケジュール、メール送信に対応した日報機能。全リソースを網羅するバージョン管理APIレスト（`/api/v1`、Bearer認証、ページネーション）。進捗表示とバックアップ／ロールバックの自動セーフティネットを備えたGitHub Releasesからのワンクリック自己更新。単一かつドライバー非依存のEloquentマイグレーションによるSQLite・MySQL対応。日本語・英語・フランス語の翻訳。