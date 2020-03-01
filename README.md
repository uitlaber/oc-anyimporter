Example config
```
Company:
    model: \MihailBishkek\Birzha\Models\User
    primaryKey: id
    unique: email,phone
    fields:
        company_name: column-2
        email: '@default:column-6,#fake_email'
        name: '@own:email'
        username: '@own:email'
        phone: column-7
        logo: column-9
        town_id: '@searchInModel:\MihailBishkek\Birzha\Models\Town,name,column-3,id'
        password: "@var:secret117"
        password_confirmation: "@var:secret117"
        is_company: "@var:1"
        is_showmarket: "@var:1"
        is_activated: "@var:1"
        logo: '@attachOne:url,column-9,logo'

Stone:
    model: \MihailBishkek\Birzha\Models\Stone
    primaryKey: id
    fields:
        title: column-0
        price: column-1
        category_id: '@array_rand:164,165'
        is_active: '@var:1'
        desc: '@markdownify:column-8'
        user_id: '@type:Company,id'
        photos: '@attachMany:file,column-17,photos'
        materials: '@belongsToMany:\MihailBishkek\Birzha\Models\Material,title,column-19,materials'
```
