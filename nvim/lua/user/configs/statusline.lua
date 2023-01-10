local M = {
    enabled = true
}

function M.install(use)
    use 'nvim-lualine/lualine.nvim' -- Fancier statusline
end

function M.options()
    vim.o.laststatus = 3
end

function M.configure()
    -- See `:help lualine.txt`
    require('lualine').setup {
        options = {
            theme = 'onedark',
            disabled_filetypes = {
                statusline = {'neo-tree'}
            }
        }
    }
end

return M
