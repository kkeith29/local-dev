local M = {
    enabled = true
}

function M.install(use)
    use 'navarasu/onedark.nvim' -- Theme inspired by Atom
end

function M.options()
    vim.o.termguicolors = true
    vim.cmd [[colorscheme onedark]]

    local colors = require('onedark.colors')
    vim.api.nvim_set_hl(0, 'DapUIBreakpointSign', {fg = colors.red})
end

return M
