local M = {
    enabled = true,
    priority = 85
}

function M.install(use)
    use 'EdenEast/nightfox.nvim'
end

function M.options()
    vim.o.termguicolors = true
    vim.cmd [[colorscheme carbonfox]]

    local cf = require('nightfox.palette.carbonfox').palette

    vim.api.nvim_set_hl(0, 'DapBreakpointSign', {fg = cf.red.base})
    vim.api.nvim_set_hl(0, 'DapBreakpointConditionalSign', {fg = cf.orange.base})
    vim.api.nvim_set_hl(0, 'DapStoppedSign', {fg = cf.green.bright})
end

return M
