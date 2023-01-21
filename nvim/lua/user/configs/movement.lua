local M = {}

function M.install(use)
    use { 'phaazon/hop.nvim', branch = 'v2' }

    use 'christoomey/vim-tmux-navigator'
end

function M.configure()
    require('hop').setup()

    vim.g.tmux_navigator_no_mappings = 1
end

function M.keymaps()
    local km = require('user.keymap')

    local opts = { silent = true }
    km.nnoremap('<C-w>h', ':<C-U>TmuxNavigateLeft<cr>', 'Move to left window', opts)
    km.nnoremap('<C-w>j', ':<C-U>TmuxNavigateDown<cr>', 'Move to window below', opts)
    km.nnoremap('<C-w>k', ':<C-U>TmuxNavigateUp<cr>', 'Move to window above', opts)
    km.nnoremap('<C-w>l', ':<C-U>TmuxNavigateRight<cr>', 'Move to right window', opts)

    -- Setup Hop
    local hop = require('hop')
    km.map('<leader>hc', function() hop.hint_char1({ current_line_only = true }) end, '[H]op to [C]har within line - Improved f/F')

    km.nnoremap('<leader>hw', function() hop.hint_words() end, '[H]op to any [w]ord in document')
    km.nnoremap('<leader>hW', function() hop.hint_words({ multi_windows = true }) end, '[H]op to any [W]ord in any window')

    km.nnoremap('<leader>hl', function() hop.hint_lines() end, '[H]op to any [l]ine in document')
    km.nnoremap('<leader>hL', function() hop.hint_lines({ multi_windows = true }) end, '[H]op to any [L]ine in any window')

    km.nnoremap('<leader>hs', function() hop.hint_patterns() end, '[H]op to pattern [s]earch in document')
    km.nnoremap('<leader>hS', function() hop.hint_patterns({ multi_windows = true }) end, '[H]op to pattern [S]earch in any window')
end

return M
