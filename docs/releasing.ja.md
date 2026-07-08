# リリースの作成手順

🌐 [English](releasing.md) · [Français](releasing.fr.md) · **日本語**

このドキュメントは、デプロイ済みの各インスタンスが `/admin/backup`（`GithubUpdateService` を参照）から自動的に検知・適用できるように、Kintaiの新バージョンをGitHub上で公開する手順を説明します。

## 仕組み

自動アップデート機能（`GithubUpdateService::checkLatestRelease()`）は `GET /repos/{GITHUB_UPDATE_REPO}/releases/latest` を呼び出し、GitHubがそのリリースのタグ向けに自動生成するソースアーカイブ（`zipball_url`）をダウンロードします。**手動でビルドやアップロードを行う必要は一切ありません** — `main` 上に `vX.Y.Z` というタグ付きのGitHub Releaseが存在すれば十分です。

留意点：
- カウントされるのは **GitHub Release** のみです（単なるタグや `main` へのコミットは対象外）。Releaseが存在しない限り、`checkLatestRelease()` は `null` を返します。
- タグは必ず `main` から作成してください — 本番インスタンスが追随するのはこのブランチです。
- バージョン番号は [semver](https://semver.org/)（`メジャー.マイナー.パッチ`）に従い、設定ファイル内では `v` プレフィックスを付けません（`v` プレフィックスはGitタグにのみ存在し、`GithubUpdateService` がバージョン比較の前に自動で取り除きます）。ベータ期間中は `0.0.x-beta` の形式を使用します。

## 前提条件

- [GitHub CLI](https://cli.github.com/)（`gh`）がインストール・認証済み（`gh auth login`）であること。
- `main` ブランチ上で、`origin/main` と同期しており、作業ツリーがクリーンであること（`git status` が空）。
- `CHANGELOG.md` の `## [Unreleased]` セクションに、公開予定の内容がすべて反映されていること — この内容がそのままリリースノートになります。

## 自動化された手順（推奨）

```powershell
# プレビューのみ。何も変更・公開しない
.\scripts\release.ps1 -Version 0.1.0-beta -DryRun

# 実際に公開する
.\scripts\release.ps1 -Version 0.1.0-beta
```

`scripts/release.ps1` は以下を行います：

1. 前提条件を確認する（`gh` のインストール・認証、`main` ブランチにいること、作業ツリーがクリーンであること、`origin/main` に対して遅れていないこと、タグがまだ存在しないこと）。
2. `CHANGELOG.md` 内の `## [Unreleased]` を `## [0.1.0-beta] - YYYY-MM-DD` に変換し、その上に空の `## [Unreleased]` セクションを再度追加する。
3. `composer.json` のバージョン番号と、`config/app.php` のデフォルト `APP_VERSION` を更新する。
4. このバージョン更新をコミットし（`chore(release): v0.1.0-beta`）、注釈付きタグ `v0.1.0-beta` を作成、ブランチとタグをプッシュする。
5. `## [Unreleased]` にあった内容をノートとして、GitHub Release を作成する（`gh release create`）。

## 手動での手順（スクリプトが使えない場合）

1. `main` ブランチに移動し、最新の状態にする：
   ```bash
   git checkout main
   git pull
   ```
2. `CHANGELOG.md` 内の `## [Unreleased]` を `## [0.1.0-beta] - 2026-07-08` に変更し、その直上に新しい空の `## [Unreleased]` セクションを追加する。
3. `composer.json`（`"version": "0.1.0-beta"`）と `config/app.php`（`env('APP_VERSION', '0.1.0-beta')`）のバージョンを更新する。
4. コミットしてプッシュする：
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "chore(release): v0.1.0-beta"
   git push
   ```
5. タグとリリースを作成する：
   ```bash
   git tag -a v0.1.0-beta -m "Kintai v0.1.0-beta"
   git push origin v0.1.0-beta
   gh release create v0.1.0-beta --title "Kintai v0.1.0-beta" --notes-file notes.txt
   ```
   ここで `notes.txt` には、変更履歴に追加したばかりのバージョンセクションの内容を記載します。

## 公開後

- 各インスタンスでは、オーナーに `/admin/backup` 上で「アップデートが利用可能です」と表示され、「今すぐ更新」をクリックできます。
- 何らかの適用を行う前に、インスタンスは自動的にデータベース＋アップロードファイルのバックアップと、コードのスナップショットを `storage/backups/` に作成します。
- `composer.lock` に変更があった場合、インスタンスは自動的に `composer install` を試みます（ベストエフォート）。失敗または実行できない場合は、SSH経由で手動実行するよう警告が表示されます。

## 問題が発生した場合

- **誤って公開した／壊れたリリース：** `gh release delete vX.Y.Z` を実行後、ローカルで `git push --delete origin vX.Y.Z` と `git tag -d vX.Y.Z` を実行してください。すでにアップデートを適用したインスタンスは自動的にはロールバックされません — アップデート直前に `storage/backups/` に作成されたDB＋アップロードのバックアップとコードスナップショットから復元してください。
- **`gh` が未認証：** `gh auth login` を実行後、再試行してください。
- **リポジトリがいつか非公開になった場合：** 各インスタンスで `GITHUB_UPDATE_TOKEN`（`.env.example` を参照）を設定してください。`gh release create` はローカルの `gh` 認証で既に動作するため、スクリプト側の変更は不要です。
