# データベース戦略：Kintai

🌐 [English](database.md) · [Français](database.fr.md) · **日本語**

## 📊 概要
Kintaiは唯一のデータアクセス層として **Eloquent ORM**（`illuminate/database` ^11.0）を使用しています。SQLite（デフォルト、設定不要）とMySQL/MariaDB（本番規模）に対応 — `config/database.php` でドライバーを選択する、ドライバー非依存の設計です。

## 🛤 Eloquent ORM

### 初期化
`DatabaseServiceProvider`（`src/Core/Database/DatabaseServiceProvider.php`）が `Illuminate\Database\Capsule\Manager` 経由で起動します。

### モデル（`src/Domain/Eloquent/`）
36個の `final` Eloquentモデルがあり、いずれも同じパターンに従います：`$guarded = []`、`$timestamps = false`、リレーション/キャスト以外のロジックは持ちません。ドメイン別の分類：

- **テナンシー：** `User`、`Store`、`StoreUser`
- **シフト管理：** `Shift`、`ShiftType`、`Availability`、`ShiftClaim`、`ShiftSwapRequest`
- **勤怠・給与：** `Timeclock`、`TimeoffRequest`、`UserShiftTypeRate`、`StoreDeductionSetting`
- **日報・人事：** `DailyReport`、`HiringReport`、`ResignationReport`、`SalaryReport`、`Feedback`
- **コミュニケーション：** `MessageThread`、`ThreadMessage`、`ThreadParticipant`、`Notification`
- **システム・設定：** `ActivityEntry`、`AppSetting`、`ApiToken`、`CronToken`、`IcalToken`、`PasswordResetToken`、`ImportAlias`、`StoreFeature`、`StoreImportSetting`、`StorePhotoImage`、`StorePhotoSubmission`、`UserDashboardPref`、`UserNavPref`
- **i18n：** `Language`、`Translation`

常に最新かつ正確な一覧は `ls src/Domain/Eloquent/` を実行して確認してください。

### リポジトリパターン
`src/Core/Repositories/` にある30個のリポジトリがEloquentモデルをラップします。コントローラーやサービスがEloquentモデルを直接扱うことは**禁止**されており、必ず `RepositoryServiceProvider` にバインドされた注入済みのリポジトリインターフェース経由で行います。

## 🔄 マイグレーションシステム
PHPベースで統一されており、1つのマイグレーションファイルでSQLiteとMySQLの両方をカバーします。生SQLやドライバー別の重複はありません。

- **格納場所：** `database/migrations/php/`（41マイグレーション）
- **実行コマンド：** `php scripts/db-migrate.php`（プレビューは `--dry-run`）
- **基底クラス：** `kintai\Core\Database\Migration`
- **冪等性：** `$this->schema()->hasTable()` によるガードにより、何度実行しても安全です。

## 🗄 対応ドライバー
- **SQLite：** デフォルト。設定不要のファイルベースで、シングルテナントのデプロイに適しています。
- **MySQL / MariaDB：** より高い書き込み並行性や既存のホスティングインフラを活用したい場合に。注：SQLiteでは `ON DELETE CASCADE` が適用されないため、依存する行の削除はリポジトリのコード内で明示的に行われ、両ドライバー間で動作の一貫性を保っています。

## 💾 バックアップと可搬性
- 各インスタンスはデータベースとアップロードファイルの両方を含む完全なSQLダンプ（`BackupService`）をトリガーできます。
- 各テナントは単一かつ自己完結したデータベースであるため、自社ホスティングへの移行はDBダンプとKintaiのソースコードだけで完了し、データ抽出の手順は不要です。
