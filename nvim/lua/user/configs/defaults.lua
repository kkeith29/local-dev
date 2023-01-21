local M = {
    enabled = true,
    priority = 90
}

function M.options()
    -- Tab
    vim.o.tabstop = 4
    vim.o.softtabstop = 4
    vim.o.shiftwidth = 4
    vim.o.expandtab = true
    vim.o.autoindent = true
    vim.o.smartindent = true

    -- Set color column
    vim.o.cc = '120'

    -- Set search options
    vim.o.hlsearch = false
    vim.o.incsearch = true
    vim.o.ignorecase = true
    vim.o.smartcase = true

    -- Make line numbers default
    vim.wo.number = true
    vim.wo.relativenumber = true

    -- Enable mouse mode
    vim.o.mouse = 'a'

    -- Enable break indent
    vim.o.breakindent = true

    -- Save undo history
    vim.o.undofile = true
    vim.o.undodir = os.getenv('HOME') .. '/.vim/undodir'
    vim.o.swapfile = false
    vim.o.backup = false

    -- Disable wrapping
    vim.o.wrap = false

    -- Decrease update time
    vim.o.updatetime = 250
    vim.wo.signcolumn = 'yes'

    -- Set completeopt to have a better completion experience
    vim.o.completeopt = 'menuone,noselect'

    -- Set amount of text to show when scrolling
    vim.o.scrolloff = 8
end

function M.keymaps()
    -- See `:help mapleader`
    --  NOTE: Must happen before plugins are required (otherwise wrong leader will be used)
    vim.g.mapleader = ' '
    vim.g.maplocalleader = ' '

    -- Keymaps for better default experience
    -- See `:help vim.keymap.set()`
    vim.keymap.set({ 'n', 'v' }, '<Space>', '<Nop>', { silent = true })

    -- Remap for dealing with word wrap
    vim.keymap.set('n', 'k', "v:count == 0 ? 'gk' : 'k'", { expr = true, silent = true })
    vim.keymap.set('n', 'j', "v:count == 0 ? 'gj' : 'j'", { expr = true, silent = true })

    local km = require('user.keymap')
    local nvnoremap = km.factory({ 'n', 'v' }, { noremap = true })

    -- Global bindings
    km.nnoremap('U', '<C-r>', 'Redo (Inverse of u)')
    km.nnoremap('+', '<C-a>', 'Increment number')
    km.nnoremap('-', '<C-x>', 'Decrement number')
    km.nnoremap('<C-a>', 'ggVG', 'Select entire document')
    km.nnoremap('Y', 'y$', 'Yank until end of line (like other capitals)')
    km.nnoremap('<C-z>', '<Nop>', 'Disable suspension', { silent = true })
    nvnoremap('H', '^', 'Go to beginning of line')
    nvnoremap('L', '$', 'Go to end of line')

    -- Window splits
    km.nnoremap('<C-w>s', ':vsplit<cr>', 'Split window')
    km.nnoremap('<C-w>S', ':split<cr>', 'Split window')

    -- Window resize
    km.nnoremap('<C-w>+', ':resize +5<cr>', 'Increase window height', { silent = true })
    km.nnoremap('<C-w>-', ':resize -5<cr>', 'Decrease window height', { silent = true })
    km.nnoremap('<C-w><', ':vertical resize -5<cr>', 'Decrease window width', { silent = true })
    km.nnoremap('<C-w>>', ':vertical resize +5<cr>', 'Increase window width', { silent = true })

    -- Update actions to not change registers
    nvnoremap('c', '"_c', 'Send change actions to black hole register')
    nvnoremap('d', '"_d', 'Send delete actions to black hole register')
    nvnoremap('D', '"_D', 'Send delete actions to black hole register')
    km.nnoremap('x', '"_x', 'Send cut actions to black hole register')

    -- Update visual mode ident/dedent
    km.vnoremap('<', '<gv', 'Deident and reselect')
    km.vnoremap('>', '>gv', 'Indent and reselect')

    -- Maintain cursor position when yanking a visual selection
    km.vnoremap('y', 'myy`y', 'Yank text and maintain cursor position')
    km.vnoremap('Y', 'myY`y', 'Yank line and maintain cursor position')

    -- Keep it centered
    km.nnoremap('n', 'nzz', 'Next result and stay centered')
    km.nnoremap('N', 'Nzz', 'Previous result and stay centered')

    -- System clipboard
    km.vnoremap('<Leader>y', '"+y', 'Yank text to system clipboard')
    km.vnoremap('<Leader>x', '"+x', 'Cut text to system clipboard')
    km.nnoremap('<Leader>y', '"+yy', 'Yank line to system clipboard')

    nvnoremap('<Leader>p', '"+p', 'Paste after line from system clipboard')
    nvnoremap('<Leader>P', '"+P', 'Paste before line from system clipboard')

    -- Buffer handling
    local opts = { silent = true }
    km.nnoremap('<leader>w', ':write<cr>', '[W]rite buffer', opts)
    km.nnoremap('<leader>x', ':write<cr>:Bdelete<cr>', 'Write and delete buffer without closing window', opts)
    km.nnoremap('<leader>q', ':Bdelete!<cr>', 'Forcefully [q]uit buffer without closing window', opts)
    km.nnoremap('<leader>Q', ':bufdo bdelete!<cr>', 'Forcefully [Q]uit all buffers without closing window', opts)
    km.nnoremap('<C-l>', ':bnext<cr>', 'Go to next buffer', opts)
    km.nnoremap('<C-h>', ':bprevious<cr>', 'Go to previous buffer', opts)

    -- Navigation mappings [g = go]
    km.nnoremap('gb', '<C-o>', '[G]o [B]ack')
    km.nnoremap('gf', '<C-i>', '[G]o [F]orward')

    -- File related mappings [f = file]
    local telescope = require('telescope.builtin')
    km.nnoremap('<Leader>fe', ':Neotree toggle<cr>', 'Toggle [F]ile [E]xplorer', opts)
    km.nnoremap('<Leader>ff', ':Neotree reveal<cr>', '[F]ocus current [F]ile in file explorer', opts)
    km.nnoremap('<leader>fo', telescope.find_files, '[F]ile - [O]pen')
    km.nnoremap('<leader>fs', telescope.live_grep, '[F]ile - [S]earch by grep')
    km.nnoremap('<leader>fb', telescope.buffers, '[F]ile - Find [B]uffers')
    km.nnoremap('<leader>fp', telescope.oldfiles, '[F]ile - Find [P]reviously opened files')

    -- Diagnostic keymaps [d = diagnostic]
    km.nnoremap('<leader>dj', vim.diagnostic.goto_prev, '[D]iagnostic - [P]revious')
    km.nnoremap('<leader>dk', vim.diagnostic.goto_next, '[D]iagnositc - [N]ext')

    -- [[ Highlight on yank ]]
    -- See `:help vim.highlight.on_yank()`
    local highlight_group = vim.api.nvim_create_augroup('YankHighlight', { clear = true })
    vim.api.nvim_create_autocmd('TextYankPost', {
        callback = function()
            vim.highlight.on_yank()
        end,
        group = highlight_group,
        pattern = '*',
    })
end

return M
