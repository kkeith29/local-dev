local M = {
    enabled = true,
    languages = {
        'c', 'cpp', 'go', 'lua', 'rust', 'typescript', 'help', 'vim', 'javascript', 'php', 'comment', 'phpdoc', 
        'markdown'
    }
}

function M.install(use)
    use { -- Highlight, edit, and navigate code
        'nvim-treesitter/nvim-treesitter',
        run = function()
            pcall(require('nvim-treesitter.install').update { with_sync = true })
        end,
    }

    use 'nvim-treesitter/playground'

    use { -- Additional text objects via treesitter
        'nvim-treesitter/nvim-treesitter-textobjects',
        after = 'nvim-treesitter',
    }
end

function M.configure()
    -- See `:help nvim-treesitter`
    require('nvim-treesitter.configs').setup {
        -- Add languages to be installed here that you want installed for treesitter
        ensure_installed = M.languages,

        highlight = { enable = true },
        indent = { enable = true, disable = { 'python' } },
        incremental_selection = {
            enable = true,
            keymaps = {
                init_selection = '<c-space>',
                node_incremental = '<c-space>',
                scope_incremental = '<c-s>',
                node_decremental = '<c-backspace>',
            },
        },
        textobjects = {
            select = {
                enable = true,
                lookahead = true, -- Automatically jump forward to textobj, similar to targets.vim
                keymaps = {
                    -- You can use the capture groups defined in textobjects.scm
                    ['aa'] = '@parameter.outer',
                    ['ia'] = '@parameter.inner',
                    ['af'] = '@function.outer',
                    ['if'] = '@function.inner',
                    ['ac'] = '@class.outer',
                    ['ic'] = '@class.inner',
                },
            },
            move = {
                enable = true,
                set_jumps = true, -- whether to set jumps in the jumplist
                goto_next_start = {
                    [']]'] = '@function.outer'
                },
                goto_previous_start = {
                    ['[['] = '@function.outer',
                }
            },
            swap = {
                enable = true,
                swap_next = {
                    ['<leader>csp'] = '@parameter.inner',
                },
                swap_previous = {
                    ['<leader>csP'] = '@parameter.inner',
                },
            },
        },
        playground = {
            enable = true
        }
    }
end

return M
