local M = {}

local function code_generator(bufnr, file, type)
    local cmd = {'ld-cli', 'code:generate', file}
    if type then
        table.insert(cmd, '--type')
        table.insert(cmd, type)
    end
    local output = vim.fn.systemlist(cmd, nil, true);
    vim.api.nvim_buf_set_lines(bufnr, 0, -1, true, output)
end

function M.configure()
    vim.api.nvim_create_autocmd('FileType', {
        group = vim.api.nvim_create_augroup('UserPHP', { clear = true }),
        pattern = 'php',
        callback = function(ev)
            vim.opt_local.iskeyword:append('$')

            vim.keymap.set('v', '<leader>cu', '!ld-cli code:use-formatter<cr>', { buffer = ev.buf, noremap = true, silent = true })
            vim.keymap.set('n', '<leader>cg', function() code_generator(ev.buf, vim.fn.expand('%:p')) end)

            vim.api.nvim_buf_create_user_command(ev.buf, 'GeneratePhpFile', function(data)
                code_generator(ev.buf, vim.fn.expand('%:p'), data.fargs[1] or nil)
            end, { nargs = '?', desc = 'Generate PHP file' })
        end
    })
end

return M
