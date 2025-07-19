if [[ -r "${XDG_CACHE_HOME:-$HOME/.cache}/p10k-instant-prompt-${(%):-%n}.zsh" ]]; then
  source "${XDG_CACHE_HOME:-$HOME/.cache}/p10k-instant-prompt-${(%):-%n}.zsh"
fi

path+=("$HOME/.bin")

export ZSH="$HOME/.oh-my-zsh"

ZSH_THEME="powerlevel10k/powerlevel10k"
HIST_STAMPS="yyyy-mm-dd"

plugins=(git zsh-vi-mode zsh-autosuggestions zsh-syntax-highlighting web-search nvm aws composer git)

function zvm_after_lazy_keybindings() {
  bindkey -M vicmd H vi-first-non-blank
  bindkey -M vicmd L vi-end-of-line
  bindkey "^R" history-incremental-search-backward
}

source $ZSH/oh-my-zsh.sh

export EDITOR="nvim"

# aliases here
alias tink='while true; do php artisan tinker; done'
alias tink-debug='while true; do XDEBUG_MODE=debug XDEBUG_SESSION=PHPSTORM php artisan tinker; done'

[[ ! -f ~/.p10k.zsh ]] || source ~/.p10k.zsh
