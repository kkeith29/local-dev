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
    local map = km.factory({'n', 'x', 'o'})

    map('gc', function() hop.hint_char1({ current_line_only = true }) end, '[G]o to [c]har within line')

    map('gw', function() hop.hint_words() end, '[G]o to any [w]ord in document')
    map('gW', function() hop.hint_words({ multi_windows = true }) end, '[G]o to any [W]ord in any window')

    map('gl', function() hop.hint_lines() end, '[G]o to any [l]ine in document')
    map('gL', function() hop.hint_lines({ multi_windows = true }) end, '[G]o to any [L]ine in any window')

    km.nnoremap('s', function() hop.hint_patterns() end, 'Hop to [s]earch pattern in document')
    km.nnoremap('S', function() hop.hint_patterns({ multi_windows = true }) end, 'Hop to [S]earch pattern in any window')
end

return M
