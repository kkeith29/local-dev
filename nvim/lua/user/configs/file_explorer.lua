local M = {
    enabled = true
}

function M.install(use)
    use {
        'nvim-neo-tree/neo-tree.nvim',
        branch = 'v2.x',
        requires = {
            'nvim-lua/plenary.nvim',
            'nvim-tree/nvim-web-devicons',
            'MunifTanjim/nui.nvim'
        }
    }
end

function M.configure()
    vim.g.neo_tree_remove_legacy_commands = 1

    require('neo-tree').setup({
        default_component_configs = {
            git_status = {
                symbols = {
                    -- Change type
                    added     = '',
                    deleted   = '✖',
                    modified  = '',
                    renamed   = '',
                    -- Status type
                    untracked = '',
                    ignored   = '',
                    unstaged  = '',
                    staged    = '',
                    conflict  = ''
                }
            },
            diagnostics = {
                symbols = {
                    hint = '',
                    info = '',
                    warn = '',
                    error = ''
                },
                highlights = {
                    hint = 'DiagnosticSignHint',
                    info = 'DiagnosticSignInfo',
                    warn = 'DiagnosticSignWarn',
                    error = 'DiagnosticSignError'
                }
            }
        }
    })
end

return M
