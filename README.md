# Local Development
Collection of scripts which assist in my local development environment

## Setup
### Install command line tools
```bash
xcode-select --install
```

### Setup public and private keys from password storage
Ensure `id_rsa` and `id_rsa.pub` are created at `~/.ssh` and permissions are correct.
```bash
chmod 644 ~/.ssh/id_rsa.pub
chmod 600 ~/.ssh/id_rsa
```

### Install Homebrew
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### Install latest version of PHP 8
```bash
brew install php
```

### Clone local dev project onto machine where projects are stored
```bash
git clone git@github.com:kkeith29/local-dev.git /path/to/local-dev
```

### Create .bin directory
```base
mkdir ~/.bin
```

### Add bin to PATH variable in .zshrc
```bash
path+=('/path/to/.bin')
export PATH
```

### Install [Composer](https://getcomposer.org/download/)
Move composer.phar to `~/.bin/composer`

### Install CLI project dependencies
```bash
cd /path/to/local-dev/cli && composer install
```

### Symlink bin files
```bash
ln -s /path/to/local-dev/bin/ld-cli ~/.bin/ld-cli
ln -s /path/to/local-dev/bin/ld-cs-fixer ~/.bin/ld-cs-fixer
```

### Symlink dotfiles
```bash
ln -s /path/to/local-dev/dotfiles/.ideavimrc ~/.ideavimrc
```

### Run Brewfile
```bash
ln -s /path/to/local-dev/configs/Brewfile ~/Brewfile
brew bundle install
```

## Usage

### Use Statement Cleanup
The CLI tool comes with a command to format use blocks in PHP code utilizing ideavim's ability to pipe selected text to 
an external program. PHPStorms default use formatting doesn't handle PSR-12 2 level nesting well and just doesn't 
always know how to properly order things.

Within the editor, highlight the use block and press `<space>cu`. If not using VIM bindings, then type `:!ld-cli code:use-formatter`

### Code Style Fixer
An opinionated version of PHP-CS-Fixer is globally available via the `ld-cs-fixer` command. This comes preconfigured and just 
passes arguments directly to the php-cs-fixer binary. It is meant for quick clean up of a file. Larger projects shouldn't
depend on this.

You can run `ld-cs-fixer` for a specific file within IdeaVim using `<space>cf` command. If not using VIM bindings, use 
`:!ld-cs-fixer fix %`