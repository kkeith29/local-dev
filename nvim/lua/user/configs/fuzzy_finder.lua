local M = {
    enabled = true
}

function M.install(use)
    use { 'nvim-telescope/telescope.nvim', branch = '0.1.x', requires = { 'nvim-lua/plenary.nvim' } }
    use 'nvim-telescope/telescope-dap.nvim'
    use { 'nvim-telescope/telescope-fzf-native.nvim', run = 'make' }
end

function M.configure()
    -- See `:help telescope` and `:help telescope.setup()`
    local actions = require('telescope.actions')
    local telescope = require('telescope');
    telescope.setup({
        defaults = {
            default_mappings = {
                i = {
                    ['<C-c>'] = actions.close,
                    ['<C-j>'] = actions.move_selection_next,
                    ['<C-k>'] = actions.move_selection_previous,
                    ['<C-f>'] = actions.preview_scrolling_up,
                    ['<C-d>'] = actions.preview_scrolling_down,
                    ['<CR>'] = actions.select_default
                },
                n = {
                    ['<esc>'] = actions.close,
                    ['j'] = actions.move_selection_next,
                    ['k'] = actions.move_selection_previous,
                    ['gg'] = actions.move_to_top,
                    ['G'] = actions.move_to_bottom,
                    ['<C-f>'] = actions.preview_scrolling_up,
                    ['<C-d>'] = actions.preview_scrolling_down,
                    ['<CR>'] = actions.select_default
                }
            },
            sorting_strategy = 'ascending',
            layout_config = {
                prompt_position = 'top'
            }
        }
    })

    telescope.load_extension('dap')
    telescope.load_extension('fzf')
end

function M.keymaps()
    local km = require('user.keymap')
    local telescope = require('telescope.builtin')
    km.nnoremap('<leader>/', function()
        telescope.current_buffer_fuzzy_find(require('telescope.themes').get_dropdown {
            winblend = 10,
            previewer = false,
        })
    end, '[/] Fuzzily search in current buffer]')

    km.nnoremap('<leader>sh', telescope.help_tags, '[S]earch [H]elp')
    km.nnoremap('<leader>sw', telescope.grep_string, '[S]earch current [W]ord')
    km.nnoremap('<leader>sd', telescope.diagnostics, '[S]earch [D]iagnostics')
end

return M
