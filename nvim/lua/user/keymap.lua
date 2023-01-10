local M = {}

local function mergeTable(t1, ...)
    for _, table in ipairs({...}) do
        for key, value in pairs(table) do
            t1[key] = value
        end
    end
    return t1
end

function M.factory(mode, default_opts)
    default_opts = default_opts or {}
    return function(lhs, rhs, desc, opts)
        opts = mergeTable(opts or {}, default_opts, { desc = desc })
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
