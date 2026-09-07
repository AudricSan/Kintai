# リリースの作成手順

🌐 [English](../releasing.md) · [Français](releasing.fr.md) · **日本語**

このドキュメントは、デプロイ済みの各インスタンスが `/admin/update`（`GithubUpdateService` を参照）から自動的に検知・適用できるように、Kintaiの新バージョンをGitHub上で公開する手順を説明します。

## 仕組み

自動アップデート機能（`GithubUpdateService::checkLatestRelease()`）は `GET /repos/{GITHUB_UPDATE_REPO}/releases`（最新の1件だけでなく全リリースの一覧）を呼び出し、GitHubが選択されたリリースのタグ向けに自動生成するソースアーカイブ（`zipball_url`）をダウンロードします。**手動でビルドやアップロードを行う必要は一切ありません** — `vX.Y.Z` というタグ付きのGitHub Releaseが存在すれば十分です。

各インスタンスは、オーナーが `/admin/update` で選択する3つの**アップデートチャンネル**のいずれかに従います：
- **Release** — `main` から公開され、GitHub上でprereleaseとしてマークされていないリリースのみ。
- **Beta** — `main` または `beta` から公開されたリリース。
- **Alpha** — チャンネルを問わず、すべてのリリース。

リリースのチャンネルは、タグの内容ではなく `target_commitish`（公開元ブランチ。`.github/workflows/release.yml` が設定 — `GithubUpdateService::selectReleaseForChannel()` を参照）で判定されます。そのチャンネルで見えるリリースの中から、インスタンスは最も新しいバージョンを選択します（semverに準拠した比較）。`alpha`、`beta`、`main` は保護されたブランチです（PR + CIグリーンが必須、直接pushは不可） — `.github/workflows/release.yml` を参照してください。

留意点：
- カウントされるのは **GitHub Release** のみです（単なるタグやコミットは対象外）。インスタンスのチャンネルに合致するReleaseが存在しない限り、`checkLatestRelease()` は `null` を返します。
- 設定ファイル内では `v` プレフィックスを付けません（`v` プレフィックスはGitタグにのみ存在し、`GithubUpdateService` がバージョン比較の前に自動で取り除きます）。

## バージョン形式：X.Y.Z-<週の文字><サブバージョン>

バージョン番号は、安定版リリースでは `X.Y.Z`（サフィックスなし）、prereleaseでは `X.Y.Z-LN`（`L` = 文字、`N` = 数字）という形式になります：

- **X** — `main` で公開された**安定版**リリースの累計カウント。
- **Y** — `beta` で公開された**betaリリース**の累計カウント。
- **Z** — `alpha` で公開された**alphaリリース**の累計カウント。

3つのカウンタはそれぞれ**手動で**、公開先のチャンネルに対応するものだけを +1 します（他の2つはそのまま）— 詳細は下記「新バージョンの公開」を参照。

- **L** — 現在のISO週に対応する文字。`.github/workflows/release.yml` が自動計算します（表計算ソフトの列名方式のbase26エンコード：`a` = 第1週、`b` = 第2週 ... `z` = 第26週、`aa` = 第27週...）。
- **N** — サブバージョン：新しいISO週が始まるたびに1にリセットされ、その週にalpha/betaが公開されるたびに（両チャンネル合算で）自動的に増分されるカウンタ。その週にすでに公開済みのタグから算出されます。

`L` と `N` は**手動では入力しません** — 公開時にワークフローが計算します。このサフィックスが付くのは `alpha`/`beta` のリリースのみで、安定版（`main`）リリースはサフィックスなしの `vX.Y.Z` のままです。

## X・Y・Zのどれを上げるか

どのカウンタを上げるかは、（従来のsemverのMAJOR/MINOR/PATCHとは異なり）変更の大きさではなく、**公開先のチャンネル**だけで決まります：

- `alpha` に公開する場合 → **Z** を上げる（`config/app.php`/`composer.json`）。
- `beta` に公開する場合 → **Y** を上げる。
- `main`（安定版リリース）に公開する場合 → **X** を上げる。

## 新バージョンの公開（推奨フロー）

ベースのバージョン番号（`composer.json`/`config/app.php`/`CHANGELOG.md` 内の `X.Y.Z`）は、これまでどおり**手動で**更新します。`alpha`、`beta`、`main` のいずれかにバージョン更新がpushされると `.github/workflows/release.yml` が自動的に行うのは *Gitタグと GitHub Release の作成* です — タグ付けや `gh release create` を自分で実行する必要はもうありません。

1. 通常の作業ブランチ上でバージョンを更新します（下記の「手動での手順」を参照。または `scripts/release.ps1 -DryRun` を実行してCHANGELOGのノートをプレビューできます — このスクリプトの自動タグ付け/push/`gh release create` の各ステップは今回のActionに置き換えられ、保護されたブランチに対しては単純に失敗するため、`-DryRun` なしでは実行しないでください）。
2. 公開したいチャンネルのブランチ（`alpha`、`beta`、`main`）を対象にPRを作成し、CIがグリーンになったらマージします（ブランチ保護により必須）。
3. マージによって発生したpushで `.github/workflows/release.yml` が実行され、以下を行います：
   - `composer.json` からベースバージョンを読み取る
   - `alpha`/`beta` では、週の文字とサブバージョン（上記参照）を計算して `vX.Y.Z-LN` としてタグ付けし、prereleaseとしてマークする — 公開のたびに別個のタグが作られます（push毎に書き換わるローリングタグはもう使いません）
   - `main` では `vX.Y.Z` を通常の（prereleaseではない）Releaseとしてタグ付けする — このタグがすでに存在する場合（前回の安定版リリース以降バージョン更新がなかった場合）は、ログメッセージを出して何もしない
   - `CHANGELOG.md` からリリースノートを抽出する（`main` の場合は日付付きの `## [X.Y.Z]` セクション、`alpha`/`beta` の場合は `## [Unreleased]` セクション）

あるチャンネルから次のチャンネルへバージョンを昇格させる場合（alpha → beta → release）は、他のブランチ昇格と同様に、対応するブランチをPR経由で次のブランチへマージしてください（例：`alpha` を `beta` へ、続いて `beta` を `main` へ）。

## 手動での手順（バージョン更新）

1. 作業ブランチ上で、`CHANGELOG.md` 内の `## [Unreleased]` を `## [0.11.0] - 2026-08-04` に変更し（ワークフローが計算する `-LN` サフィックスを除いたベースの `X.Y.Z`）、その直上に新しい空の `## [Unreleased]` セクションを追加する。
2. `composer.json`（`"version": "0.11.0"`）と `config/app.php`（`env('APP_VERSION', '0.11.0')`）のバージョンを、公開先チャンネルに対応するカウンタだけを増分して更新する（`main` ならX、`beta` ならY、`alpha` ならZ）。
3. コミットしてブランチをpushし、`alpha`、`beta`、`main` のいずれか適切なブランチへPRを作成する：
   ```bash
   git add CHANGELOG.md composer.json config/app.php
   git commit -m "core(release): v0.11.0"
   git push -u origin <あなたのブランチ>
   gh pr create --base beta
   ```
4. マージされると、`.github/workflows/release.yml` が自動的にタグ（`alpha`/`beta` では `-LN` サフィックス付き）とGitHub Releaseを作成します — 手動で行うことはもう残っていません。

## 公開後

- 各インスタンスでは、オーナーに `/admin/update` 上で「アップデートが利用可能です」と表示され、「今すぐ更新」をクリックできます。
- 何らかの適用を行う前に、インスタンスは自動的にデータベース＋アップロードファイルのバックアップと、コードのスナップショットを `storage/backups/` に作成します。
- `composer.lock` に変更があった場合、インスタンスは自動的に `composer install` を試みます（ベストエフォート）。失敗または実行できない場合は、SSH経由で手動実行するよう警告が表示されます。

## 問題が発生した場合

- **誤って公開した／壊れたリリース：** `gh release delete vX.Y.Z` を実行後、ローカルで `git push --delete origin vX.Y.Z` と `git tag -d vX.Y.Z` を実行してください。すでにアップデートを適用したインスタンスは自動的にはロールバックされません — アップデート直前に `storage/backups/` に作成されたDB＋アップロードのバックアップとコードスナップショットから復元してください。
- **Actionがリリースの公開に失敗する：** Actionsタブで `Release` ワークフローの実行結果を確認してください。最も多い原因は、`composer.json` のバージョンがまだ `CHANGELOG.md` のどの `## [...]` セクションにも対応していないケースです — この場合、ワークフローはノートなしでリリースを公開するのではなく、意図的にビルドを失敗させます。不足しているCHANGELOGエントリを追加してから再度pushしてください。必要であれば `gh release create` で手動にタグ付け・公開することも可能です。
- **リポジトリがいつか非公開になった場合：** 各インスタンスで `GITHUB_UPDATE_TOKEN`（`.env.example` を参照）を設定し、`GithubUpdateService` がAPIへの問い合わせを継続できるようにしてください。`Release` ワークフロー自体は組み込みの `GITHUB_TOKEN` で既にリポジトリへのアクセス権を持っているため、そちら側の変更は不要です。
