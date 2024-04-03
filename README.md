# Git Diff eXtraction 
[![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/vierilusen/git-diff-x)](https://github.com/vierilusen/git-diff-x)
[![GitHub issues](https://img.shields.io/github/issues/vierilusen/git-diff-x)](https://github.com/vierilusen/git-diff-x/issues)
[![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/laravel-zero/laravel-zero/php)](https://github.com/vierilusen/git-diff-x/releases/tag/v1.0)

The purpose of this project is to provide a CLI program that can be used internally by PT Bhinneka Life Indonesia. The program is designed to identify the differences between a repository branch and its master in the development repository. Once identified, it generates a diff that can be used to deploy changes automatically to the DP_APP_DB repository.

## Require
- [PHP 8.1+](https://www.php.net/releases/)
- [Git 2.0+](https://mirrors.edge.kernel.org/pub/software/scm/git/)

## Installation
Install GDX on Windows
1. Download cli zip file from this [release link](https://github.com/vierilusen/git-diff-x/releases/)
2. Extract zip file to C:\gdx
3. Settings your enviroment variable path to use gdx globally

## Flow System
```mermaid
flowchart TD;
    A[GDX]-->|Clone/Pull| B[Repo Dev];
    A-->|Clone/Pull| C[Repo DP_APP_DB];
    B-->D[Is Repo Branch need merger first?];
    D-->|YES| G[Exit! Merger your branch with lasted master first];
    D-->|NO| I[Identified diff files]
    I-->|Copy diff files to Repo DP_APP_DB| C;
    C-->J[Identified changes files];
    J-->K[Filter Excluded files];
    K-->P[Update backup file, and fppno];
    P-->L[Push Repo DP_APP_DB to Azure];
    P-->M[Copy repo DP_APP_DB to specific local path];
```

## Usage/Examples
See all available commands and options:

```bash
C:\gdx> gdx
```

Usage:

```bash
C:\gdx> gdx generate:repo_name --option
```
List Repo Name:
- BLS_APP
- MORE COMING SOON!

List Options:
- --push (push to remote repo and create local folder DP)
- --push-only (just push to remote repo and don't create local folder DP)

Example:

```bash
C:\gdx> gdx generate:bls_app --push
```

