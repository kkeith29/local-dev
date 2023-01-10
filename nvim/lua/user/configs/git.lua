local M = {
    enabled = true
}

function M.install(use)
    use 'tpope/vim-fugitive'
    use 'tpope/vim-rhubarb'
    use 'lewis6991/gitsigns.nvim'
end

function M.configure()
    -- See `:help gitsigns.txt`
    require('gitsigns').setup {
        signs = {
            add = { text = '+' },
            change = { text = '~' },
            delete = { text = '_' },
            topdelete = { text = '‾' },
            changedelete = { text = '~' },
            untracked = { text = ' ' }
        },
    }
end

return M

