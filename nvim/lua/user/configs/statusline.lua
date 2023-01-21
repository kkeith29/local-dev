local M = {
    enabled = true
}

function M.install(use)
    use 'nvim-lualine/lualine.nvim' -- Fancier statusline
end

function M.configure()
    -- See `:help lualine.txt`
    require('lualine').setup {
        options = {
            theme = 'carbonfox',
            disabled_filetypes = {
                statusline = {'neo-tree'}
            },
            ignore_focus = {
                'dapui_watches', 'dapui_breakpoints',
                'dapui_scopes', 'dapui_console',
                'dapui_stacks', 'dap-repl'
            }
        }
    }
end

function M.options()
    vim.o.laststatus = 3
end

return M
