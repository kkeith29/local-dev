local M = {}

local callback = nil

function M.handle_repeat()
    if callback then
        callback()
    end
end

local function repeatable(cb)
    return function()
        vim.go.operatorfunc = "v:lua.require'user.keymap'.handle_repeat"
        callback = cb;
        return 'g@l';
    end
end

function M.factory(mode, default_opts)
    default_opts = default_opts or {}
    return function(lhs, rhs, desc, opts)
        opts = vim.tbl_deep_extend('force', opts or {}, default_opts, { desc = desc })
        if opts.repeatable then
            if type(rhs) ~= 'function' then
                error('RHS value must be a function when using repeatable')
            end
            rhs = repeatable(rhs)
            opts.expr = true
            opts.repeatable = nil
        end
        vim.keymap.set(mode, lhs, rhs, opts)
    end
end

local factory_cache = {}

function M.map(lhs, rhs, desc, opts)
    if not factory_cache['map'] then
        factory_cache['map'] = M.factory('')
    end
    factory_cache['map'](lhs, rhs, desc, opts);
end

function M.nnoremap(lhs, rhs, desc, opts)
    if not factory_cache['nnoremap'] then
        factory_cache['nnoremap'] = M.factory('n', { noremap = true })
    end
    factory_cache['nnoremap'](lhs, rhs, desc, opts);
end

function M.vnoremap(lhs, rhs, desc, opts)
    if not factory_cache['vnoremap'] then
        factory_cache['vnoremap'] = M.factory('v', { noremap = true })
    end
    factory_cache['vnoremap'](lhs, rhs, desc, opts);
end

return M
