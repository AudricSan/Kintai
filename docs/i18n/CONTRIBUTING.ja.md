# Kintaiへのコントリビュート

🌐 [English](../../CONTRIBUTING.md) · [Français](CONTRIBUTING.fr.md) · **日本語**

ご興味を持っていただきありがとうございます！

## はじめる前に

Kintaiは **GNU Affero General Public License v3.0**（AGPL-3.0）の下で公開されています。
コントリビュートすることで、あなたの貢献も同じライセンス条件で公開されることに同意したものとします。

## 貢献の方法

### バグ報告

**Bug Report** テンプレートを使ってissueを作成してください。以下を含めてください：
- PHPのバージョンとOS
- 再現手順
- 期待される動作と実際の動作
- `storage/logs/` の関連ログ

### 機能提案

**Feature Request** テンプレートを使ってissueを作成してください。ユースケースと、それがプロジェクトの方向性に合致する理由を説明してください — プロダクトの方向性については [docs/i18n/vision.ja.md](vision.ja.md) を参照してください。

### プルリクエストの提出

1. リポジトリをフォークする
2. ブランチを作成する：`git checkout -b feat/my-feature` または `fix/my-bug`
3. 下記の規約に従って変更を行う
4. テストを実行する：`./vendor/bin/phpunit`
5. `main` ブランチに対してプルリクエストを開く

## コーディング規約

- PHP 8.3以上、すべてのファイルでstrict types（`declare(strict_types=1)`）を使用
- ネームスペースのルート：`kintai\` → `src/`
- コントローラー：`final class`、コンストラクタインジェクション、シグネチャは `method(Request $request): Response` — ルートパラメータはメソッド引数ではなく `$request->param('name')` で取得する
- 永続化は `src/Core/Repositories/*Interface.php` のリポジトリインターフェース経由で行い、`RepositoryServiceProvider` にバインドする — コントローラーやサービスがEloquentモデルを直接扱うことはない
- HTTPレベルのエラーは `src/Core/Exceptions/` の例外階層を使用する
- 新しいテーブル：`database/migrations/php/` に単一のEloquentマイグレーションを追加する — SQLiteとMySQLの両方で動作すること（ドライバー別ファイルは作らない）
- SQLiteでは `ON DELETE CASCADE` が適用されないため、依存する行の削除はリポジトリのコードで明示的に行う
- コード内のコメントはフランス語で記述する。それ以外（コミットメッセージ、PRの説明、ドキュメント）はすべて英語
- `illuminate/database`（ORMとしてのみ使用するEloquent）以外の外部フレームワーク依存を追加しない
- ビュー内でインラインの `style="..."` は使用禁止 — `public/assets/css/src/` 配下のCSSモジュールを拡張すること

## テストの実行

```bash
composer install
./vendor/bin/phpunit
```

新規または変更された機能には、`tests/Unit/`（該当する場合は `tests/Integration/`）にPHPUnitテストを追加してください。

## ドキュメントの言語

README、CHANGELOG、CONTRIBUTING、SECURITY、および `docs/` 配下のすべてのドキュメントは、英語・フランス語（`.fr.md`）・日本語（`.ja.md`）で提供されています。英語版が正となるバージョンです — 英語のドキュメントを変更した場合、翻訳の更新は歓迎されますが、PRのマージに必須ではありません。`php scripts/check-translations.php` が、翻訳の欠落や更新漏れを報告します（非ブロッキングで、push のたびにCIで実行されます）。

## まず見るべき場所

- [docs/i18n/architecture.ja.md](architecture.ja.md) — フレームワークの構成
- [docs/i18n/database.ja.md](database.ja.md) — モデル、リポジトリ、マイグレーション
- [CHANGELOG.ja.md](CHANGELOG.ja.md) — 最近の変更内容
