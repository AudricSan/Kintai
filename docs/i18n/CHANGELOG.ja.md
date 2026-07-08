# Changelog（変更履歴）

🌐 [English](../../CHANGELOG.md) · [Français](CHANGELOG.fr.md) · **日本語**

Kintaiの主要な変更履歴をここに記録しています。

## [Unreleased]

### 変更
- 翻訳ドキュメント（`*.fr.md`、`*.ja.md`）をすべて `docs/i18n/` に移動しました。以前は英語版ソースと同じ `docs/` 内に置かれていたものも含みます。ルートの `SECURITY.md` の翻訳とのファイル名の大文字小文字非依存の衝突を避けるため、`docs/security.md` は `docs/security-overview.md` にリネームされました。`scripts/check-translations.php` は、ソースドキュメントのフォルダに関わらず、常に `docs/i18n/` 配下の翻訳を探すようになりました。
- `docs/database.md` を更新し、実際の `illuminate/database` のバージョン（^11.0ではなく^13.19）を反映するとともに、`Language`/`Translation` のリポジトリインターフェースが現在Eloquentではなく、JSONファイルベースの実装にバインドされていることを明記しました — 対応する `Database*Repository` は、i18nのJSON移行以前からの未使用のデッドコードです。
- 「バックアップ＆アップデート」の統合された設定タブを、**バックアップ**と**アップデート**の2つのタブに分割しました。GitHubによる自己アップデートUI（バージョン、保留中のマイグレーション、進捗表示付きワンクリックアップデート）は独自のページ `/admin/update` に移動し、`/admin/backup` は作成・復元・削除のみを扱います。内部の `POST /admin/backup/update(/stream)` および `/migrate` ルートは、`/admin/update/apply`、`/admin/update/stream`、`/admin/update/migrate` に移動しました。
- `UpdateService::getCurrentVersion()` は、`storage/app/version.json` の個別の `version` フィールドを追跡する代わりに、インストール済みバージョンを `config/app.php`（各リリースで更新され、自己アップデート処理により同期される）から直接読み取るようになりました。同ファイルは引き続き `installed_at`／`updated_at`／`duration_seconds` を記録しますが、アプリのバージョンの正とはなりません。
- `docs/vision.md` を改訂：ターゲット市場を、日本優先／世界の中小企業という区分ではなく、業種（小売業、飲食業、その他シフト制の複数拠点業種）で捉え直しました。最後のスローガンも更新しました。
- `docs/business-model.md` を改訂：料金プランの箇条書きを、Community／Pro／Business／Enterpriseの表に変更し、有料プランはマネージドSaaS上でデフォルトで何を有効化・サポートするかを決めるものであり、オープンソースのコードベースに存在する内容を制限するものではないと明記しました。直接営業のメッセージングも、もはや日本に絞ったものではありません。
- `docs/architecture.md`：Webhookの「（計画中）」の行を削除しました（もはやロードマップ上にありません）。人事レポート（採用・退職・給与）と店舗写真は、現在はバンドルではなくCore内に存在しており、将来的にバンドルへ移行する候補であることを明記しました。
- `docs/security-overview.md`：新しい業種ベースの位置付けに合わせ、「日本の労働法」を「各国の労働法」に一般化しました。
- `docs/i18n/database.fr.md` と `docs/i18n/database.ja.md` を英語版ソースと同期させました（Eloquentのバージョン、`Language`/`Translation` リポジトリのデッドコードに関する注記）。

### 修正
- `storage/app/database.sqlite` が存在しない場合（例：以前のインストールが不完全だった、または削除された場合）に、インストーラーが「Database file at path [...] does not exist.」で失敗しなくなりました — フレームワーク起動前に、ファイルとそのディレクトリが自動的に作成されるようになりました。
- `docs/releasing.md`（およびそのFR/JA翻訳）が、自己アップデートUIについて依然として `/admin/backup` を指していた問題を修正し、設定タブの分割に合わせて `/admin/update` を指すようにしました。
- **セキュリティ：** MessagingバンドルとDailyReportバンドルの `/api/v1/*` ルートは、他のすべての `/api/v1/*` エンドポイントとは異なり `ApiAuthMiddleware` なしで登録されていました — コントローラーは認証済みの `auth_user` を前提としていたにもかかわらず、Bearerトークンなしでアクセス可能な状態でした。両バンドルの `routes.php` は、CoreのAPIと同じ保護されたグループ内でAPIルートをラップするようになりました。

## [0.0.3-beta] - 2026-07-08

### 追加
- **初回パブリックベータ公開。** 充実したシフト管理機能（リスト・カレンダー・週・日・ガントチャート形式のタイムライン表示、ドラッグ＆ドロップ、一括操作、印刷、重複コンフリクトの検出、オープンシフトとシフト応募制度、視覚解析と信頼度スコアリングによるExcelインポート）。勤怠管理（リアルタイムタイマー付き打刻、管理者による編集、給与自動概算）。従業員向けセルフサービス（休暇申請、シフト交換、週次の勤務可能時間登録、iCal連携、フィードバックウィジェット）。リアルタイム更新（SSE）対応のスレッド形式社内メッセージングと通知センター。下書き→提出→承認のワークフロー、PDF出力（CJK対応）、自動承認スケジュール、メール送信に対応した日報機能。全リソースを網羅するバージョン管理APIレスト（`/api/v1`、Bearer認証、ページネーション）。進捗表示とバックアップ／ロールバックの自動セーフティネットを備えたGitHub Releasesからのワンクリック自己更新。単一かつドライバー非依存のEloquentマイグレーションによるSQLite・MySQL対応。日本語・英語・フランス語の翻訳。