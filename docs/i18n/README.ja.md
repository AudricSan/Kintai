# 🕒 Kintai

🌐 [English](../../README.md) · [Français](README.fr.md) · **日本語**

**複数店舗を運営する企業向けの、オープンソースなシフト・勤怠・労務管理システム。**

[![Version](https://img.shields.io/badge/version-0.10.7-purple.svg)](CHANGELOG.ja.md)
[![License: AGPL v3](https://img.shields.io/badge/license-AGPL--3.0-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.3-8892bf.svg)](https://php.net)
[![Tests](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml/badge.svg)](https://github.com/AudricSan/Kintai/actions/workflows/tests.yml)
[![Architecture](https://img.shields.io/badge/architecture-custom%20MVC-orange.svg)]()

Kintaiは、Excelの勤怠表と大企業向けERPの間を埋めるツールです。複数店舗を展開する小売・飲食業向けに、シフト管理、勤怠打刻、休暇申請、シフト交換、日報、給与概算までを一括で提供します。自社インフラ上で、自社データを保持したまま運用できます。

> **ステータス：ベータ版（`0.7.9`）。** Kintaiは実用段階にあり、下記のデモ環境でも日常的に稼働していますが、安定版 `1.0.0` に至るまでAPIやデータスキーマが変更される可能性があります。詳細は [CHANGELOG.ja.md](CHANGELOG.ja.md) をご覧ください。

---

## 🌍 デモを試す

**デモ環境：** [kintai-lv1b.onrender.com](https://kintai-lv1b.onrender.com)
*（無料プランのインスタンスのため、初回アクセス時は起動に30〜60秒かかることがあります。データは再起動のたびにリセットされます。）*

| 権限 | メールアドレス | パスワード |
| :--- | :--- | :--- |
| スーパー管理者 | `admin@kintai.local` | `Admin1234!` |
| 従業員 | `alice.martin@kintai.local` | `Staff1234!` |

---

## Kintaiが選ばれる理由

- **データは自分たちのもの。** マルチテナントのブラックボックスではなく、導入ごとに専用インスタンス・専用データベースを持ちます。デモ環境から自社サーバーへの移行も、データベースのダンプと `git clone` だけで完了します。ベンダーロックインはありません。
- **本物のマルチ店舗運用を想定。** 店舗ごとのタイムゾーン、通貨、休憩ルール、人員不足のしきい値、機能の個別有効化に対応 — 単一店舗向けツールを無理やり拡張したものではありません。
- **日本市場に最適化、世界でも利用可能。** CJK対応のPDF出力、「姓 名」の氏名順、円・ユーロ・ドル表示、日本語・英語・フランス語の翻訳を標準搭載 — 日本の小売業の商習慣を前提に設計しつつ、他国でも利用可能です。
- **余計なフレームワークのコストがない。** 約600ファイルからなる独自のPHP 8.3コア（Laravel、Symfonyなし）。ORMとしてEloquentのみを利用。月額5ユーロ程度のVPSでも、Dockerでも同様に動作します — 詳細は [docs/i18n/architecture.ja.md](architecture.ja.md) をご覧ください。
- **実際にテストされています。** コア、リポジトリ、コントローラーをカバーする589件のPHPUnitテストが、GitHub Actions上でプッシュのたびに実行されます。

---

## 主な機能

**シフト管理** — シフト/シフト種別のCRUD、リスト・カレンダー・週・日・ガントチャート形式のタイムライン表示、ドラッグ＆ドロップ、一括操作、印刷、重複コンフリクトの検出、オープンシフトとシフト応募制度、視覚解析と信頼度スコアリングによるExcelインポート。

**勤怠管理** — リアルタイムタイマー付きの打刻（クロックイン/アウト）、管理者による打刻編集、シフト・休憩・時給・控除に基づく給与自動概算。

**従業員向けセルフサービス** — 個別ダッシュボード、週次の勤務可能時間登録、休暇申請、シフト交換、iCal連携、フローティング型フィードバックウィジェット。

**コミュニケーション** — スレッド形式の社内メッセージング（SSEによるリアルタイム更新）、既読/未読状態とトースト通知を備えた通知センター。

**日報** — 売上・来客数・人件費などの店舗別KPIレポート、下書き→提出→承認のワークフロー、PDF出力（mPDF、CJKフォント対応）、自動承認スケジュール、メール送信。

**REST API** — バージョン管理された `/api/v1`、Bearerトークン認証、ページネーション対応で、主要リソースを網羅。リファレンス：[.wiki/API.md](https://github.com/AudricSan/Kintai/wiki/API) *（ベータ移行前のリポジトリから移行中）*。

**運用機能** — GitHub Releasesからのワンクリック自己更新（進捗バーとバックアップ／ロールバックの自動セーフティネット付き）、SQLite/MySQLのバックアップと復元、外部スケジューラー向けのトークン保護されたcronエンドポイント。

詳細は [docs/i18n/architecture.ja.md](architecture.ja.md) と [docs/i18n/database.ja.md](database.ja.md) をご覧ください。

---

## 権限と業務フロー

| 権限 | 範囲 |
| :--- | :--- |
| スーパー管理者 | 全店舗、監査ログ、全体設定 |
| 店舗マネージャー/管理者 | 担当店舗のシフト、人員、休暇、交換、レポート、統計 |
| スタッフ | 自分のシフト、打刻、休暇、交換、メッセージ、フィードバック |

典型的な流れ：シフト作成・インポート → コンフリクト確認 → 従業員へ通知 → 勤怠打刻 → 日報承認 → 給与概算。

---

## はじめに

### 動作要件

- PHP 8.3以上、Composer 2.x
- `pdo_sqlite` または `pdo_mysql`、`mbstring`、`gd`、`intl`

### ローカルインストール

```bash
git clone https://github.com/AudricSan/Kintai.git
cd Kintai
composer install
php -S 127.0.0.1:8000 -t public
```

続けて `http://127.0.0.1:8000/install.php` を開いてください — Webインストーラーが `config/database.local.php` を作成し、マイグレーションを実行、管理者アカウントを作成します。

### Docker

```bash
docker build -t kintai .
docker run -p 8080:80 kintai
```

`scripts/docker-setup.php` がSQLiteの準備とマイグレーションを行い、必要に応じてデモデータを投入します（`SEED_DEMO_DATA=true`）。環境変数の一覧は [Dockerfile](Dockerfile) を参照してください。

### アップデート

```bash
php scripts/db-migrate.php --dry-run   # 未適用のマイグレーションを確認
php scripts/db-migrate.php             # 適用する
```

オーナー権限があれば `/admin/backup` からワンクリックでアップデートも可能です。最新のGitHub Releaseを取得する前に、データベースとコードを自動でバックアップし、進捗をリアルタイムに表示します。

---

## 技術スタック

独自設計のPHP 8.3 MVC — LaravelもSymfonyも使用せず、ORMとして `illuminate/database`（Eloquent）のみを採用。SQLiteまたはMySQLを設定で切り替え可能。サーバーサイドレンダリングのPHPビュー、モジュール化されたvanilla CSS/JS、ビルドツール不要。

```
public/index.php        → フロントコントローラー
src/Core/                → フレームワーク本体：DIコンテナ、ルーター、ミドルウェア、リポジトリ、サービス
src/Bundles/*/           → 機能単位で有効化できる独立モジュール（DailyReport、Messaging）
src/UI/                  → Web/APIコントローラー、PHPビュー
public/assets/           → モジュール化されたCSS/JS、ビルド不要
database/migrations/php/ → 単一スキーマからSQLite・MySQL双方に対応するEloquentマイグレーション
```

さらに詳しく：[docs/i18n/architecture.ja.md](architecture.ja.md) · [docs/i18n/database.ja.md](database.ja.md) · [docs/i18n/multi-tenancy.ja.md](multi-tenancy.ja.md)

---

## コントリビュート

バグ報告、機能提案、プルリクエストなど、あらゆる形の貢献を歓迎します。

```bash
composer install
./vendor/bin/phpunit
```

PRを開く前に [CONTRIBUTING.ja.md](CONTRIBUTING.ja.md)（コーディング規約：strict types、コンストラクタインジェクション、マイグレーションのルールなど）をご確認ください。脆弱性を発見した場合は、公開issueではなく [SECURITY.ja.md](SECURITY.ja.md) の手順に従って非公開で報告してください。

---

## ロードマップ

**次のステップ**
- [ ] スケジューリングのタイムライン上にコンフリクトを視覚的に表示
- [ ] POST系Webルートおよび公開APIエンドポイントのセキュリティ監査の強化
- [ ] 給与計算、シフト交換、オープンシフト、通知機能を対象とした重点的なテスト追加

**将来的に**
- [ ] オフライン対応を一部含むインストール可能なPWA
- [ ] 給与データのCSV/XMLエクスポート
- [ ] Slack、LINE、Teams向けの送信Webhook
- [ ] Webインターフェースの多言語対応拡充（現状はFR/EN/JAのみ）
- [ ] 複数店舗をまたいだ分析・レポートダッシュボード
- [ ] 店舗間でのシフト交換に対応した複数店舗スケジューリング
- [ ] 複数店舗をまたいだ給与・人件費最適化
- [ ] 複数店舗の在庫・売上連携（POS、ERP、ECサイト）
- [ ] 複数店舗にまたがる従業員のパフォーマンス管理・ゲーミフィケーション
- [ ] 複数店舗における労働法コンプライアンス対応（残業、休憩、祝日）
- [ ] 従業員・マネージャー向けの複数店舗対応モバイルアプリ（iOS、Android）

変更履歴の全体は [CHANGELOG.ja.md](CHANGELOG.ja.md) をご覧ください。

---

## ライセンス

[GNU Affero General Public License v3.0](LICENSE)。Kintaiは商用利用を含め自由に利用・改変・再配布できますが、改変版をネットワークサービスとして提供する場合は、そのソースコードを公開する義務があります。全文は [LICENSE](LICENSE) を参照してください。
