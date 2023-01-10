local M = {
    enabled = true
}

function M.install(use)
    use { 'nvim-telescope/telescope.nvim', branch = '0.1.x', requires = { 'nvim-lua/plenary.nvim' } }
    use { 'nvim-telescope/telescope-fzf-native.nvim', run = 'make', cond = vim.fn.executable 'make' == 1 }
end

function M.keymaps()
    local nnoremap = require('user.keymap').nnoremap
    -- See `:help telescope.builtin`
    nnoremap('<leader>/', function()
        -- You can pass additional configuration to telescope to change theme, layout, etc.
        require('telescope.builtin').current_buffer_fuzzy_find(require('telescope.themes').get_dropdown {
            winblend = 10,
            previewer = false,
        })
    end, '[/] Fuzzily search in current buffer]')

    nnoremap('<leader>sh', require('telescope.builtin').help_tags, '[S]earch [H]elp')
    nnoremap('<leader>sw', require('telescope.builtin').grep_string, '[S]earch current [W]ord')
    nnoremap('<leader>sd', require('telescope.builtin').diagnostics, '[S]earch [D]iagnostics')
end

function M.configure()
    -- See `:help telescope` and `:help telescope.setup()`
    require('telescope').setup {
        defaults = {
            mappings = {
                i = {
                    ['<C-u>'] = false,
                    ['<C-d>'] = false,
                },
            },
        },
    }

    -- Enable telescope fzf native, if installed
    pcall(require('telescope').load_extension, 'fzf')
end

return M
