    // Delta State LGA and Wards Data
    const deltaStateWards = {
        "Aniocha North": ["Obior", "Onicha-Ugbo", "Obomkpa", "Onicha-Olona", "Issele-Azagba", "Issele-Uku I", "Issele-Uku II", "Idumuje-Unor", "Ukwu-Nzu", "Ezi"],
        "Aniocha South": ["Ogwashi-Uku Village", "Ogwashi-Uku I", "Ogwashi-Uku II", "Ubulu-Uku I", "Ubulu-Uku II", "Ubulu-Unor", "Nsukwa", "Ejeme", "Isheagu-Ewulu", "Aba-Unor", "Ubulu-Okiti"],
        "Bomadi": ["Bomadi", "Kpakiama", "Tuomo", "Ogo-Eze", "Kolafiogbene-Ekametagbene", "Ogbeinama-Okoloba", "Akugbene I", "Akugbene II", "Akugbene III", "Esanma"],
        "Burutu": ["Torugbene", "Tamigbe", "Tuomo", "Seimbiri", "Ojobo", "Buluo-Ndoro", "Ngbilebiri I", "Ngbilebiri II", "Ogbolubiri", "Obotebe", "Ogulagha"],
        "Ethiope East": ["Abraka I", "Abraka II", "Abraka III", "Agbon I", "Agbon II", "Agbon III", "Agbon IV", "Agbon V", "Agbon VI", "Agbon VII", "Agbon VIII"],
        "Ethiope West": ["Mosogar I", "Mosogar II", "Jesse I", "Jesse II", "Jesse III", "Jesse IV", "Ogharefe I", "Ogharefe II", "Ogharefe III", "Oghareki I", "Oghareki II"],
        "Ika North East": ["Owa I", "Owa II", "Owa III", "Owa IV", "Owa V", "Owa VI", "Ute-Okpu", "Ute-Ogbeje", "Idumuesah", "Umuebu", "Onyia"],
        "Ika South": ["Agbor Town I", "Agbor Town II", "Ihuozomor Ozanogogo-Alisime", "Ihuiyase I", "Ekuku-Agbor", "Ihuiyase II", "Boji-Boji I", "Boji-Boji II", "Boji-Boji III", "Abavo I", "Abavo II", "Abavo III"],
        "Isoko North": ["Iyede I", "Iyede II", "Ellu-Radheo-Ovrode", "Ofagbe", "Iluelogbo", "Owhe-Akiehwe", "Emevor", "Okpe-Isoko", "Ozoro I", "Ozoro II", "Ozoro III", "Oyede", "Otibio"],
        "Isoko South": ["Oleh I", "Oleh II", "Aviara", "Uzere", "Emede", "Olomoro", "Igbide", "Erowa-Umeh", "Enhwe-Okpolo", "Irri I", "Irri II"],
        "Ndokwa East": ["Ossissa", "Afor-Obikwele", "Abarra-Inyi-Onuaboh", "Okpai-Utchi-Beneku", "Aboh-Akarrai", "Onyia-Adia-Otuoku-Umuolu", "Ase", "Ibedeni", "Ibrede-Igbuku-Onogbokor", "Ashaka"],
        "Ndokwa West": ["Utagba-Ogbe", "Utagba-Uno I", "Utagba-Uno II", "Utagba-Uno III", "Onicha-Ukwuani", "Ogume I", "Ogume II", "Abbi I", "Abbi II", "Emu"],
        "Okpe": ["Orerokpe", "Oviri-Okpe", "Oha I", "Oha II", "Aghalokpe", "Aragba Town", "Mereje I", "Mereje II", "Mereje III", "Ughoton"],
        "Oshimili North": ["Akwukwu", "Ebu", "Illah", "Ibusa I", "Ibusa II", "Ibusa III", "Ibusa IV", "Ibusa V", "Okpanam", "Ugbolu"],
        "Oshimili South": ["Ogbele/Akpu", "Anala-Amanya", "Okwe", "Umuonaje", "Umuezei", "Umuaji", "Ogbeosowe", "West End", "Cable Point", "Okpanam Road/GRA", "Asaba Inland/All Saints"],
        "Patani": ["Patani Urban I", "Patani Urban II", "Abari", "Odouro", "Bolou-Angiama", "Ramos", "Kolowaware", "Aven", "Agoloma", "Uduophori"],
        "Sapele": ["Sapele Urban I", "Sapele Urban II", "Sapele Urban III", "Sapele Urban IV", "Sapele Urban V", "Sapele Rural", "Amuokpe", "Elume", "Okokporo/Ugborhen", "Deghele"],
        "Udu": ["Udu I", "Udu II", "Udu III", "Udu IV", "Opete/Aladja", "Orhuwhorun", "Ovwian I", "Ovwian II", "Dumez", "Otor-Udu"],
        "Ughelli North": ["Ughelli Urban I", "Ughelli Urban II", "Ughelli Urban III", "Orogun I", "Orogun II", "Agbarha", "Agbarho I", "Agbarho II", "Evwreni", "Uwheru", "Olomu I"],
        "Ughelli South": ["Ewu I", "Ewu II", "Ewu III", "Olomu I", "Olomu II", "Ughievwen I", "Ughievwen II", "Ughievwen III", "Ughievwen IV", "Effurun-Otor"],
        "Ukwuani": ["Amai", "Ezumumba", "Umututu", "Akoku", "Ebedei", "Umukwata", "Obiaruku I", "Obiaruku II", "Umuebu", "Ushie"],
        "Uvwie": ["Effurun I", "Effurun II", "Ekpan I", "Ekpan II", "Enerhen", "Ugbomro/Ugborikoko", "Army Barracks Area", "Iteregbi", "Praise Amour/Jakpa"],
        "Warri North": ["Koko I", "Koko II", "Ogbudugbudu", "Ebrohimi", "Ogheye", "Gbolukoko", "Jakpa", "Tsekelewu", "Ogbinbiri", "Opuama"],
        "Warri South": ["Obodo/Omadino", "Ode-Itsekiri", "Ogunu/Ekurede-Urhobo", "Ugbuwangue/Ekurede-Itsekiri", "G.R.A.", "Bowen", "Pessu", "Okere", "Igbudu", "Edjeba", "Okumagba I", "Okumagba II"],
        "Warri South West": ["Ogbe-Ijoh", "Isaba", "Oproza", "Gbaramatu", "Ugborodo", "Orere", "Beni-River", "Aja-Udaibo", "Ogidigben", "Madangho"]
    };

    // Populate LGA dropdown
    const lgaSelect = document.getElementById('lga');
    const wardSelect = document.getElementById('ward');

    // Clear and populate LGA dropdown
    while (lgaSelect.options.length > 0) {
        lgaSelect.remove(0);
    }

    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Select LGA';
    lgaSelect.appendChild(defaultOption);

    Object.keys(deltaStateWards).sort().forEach(lga => {
        const option = document.createElement('option');
        option.value = lga;
        option.textContent = lga;
        lgaSelect.appendChild(option);
    });

    // LGA change event
    lgaSelect.addEventListener('change', function() {
        const selectedLGA = this.value;
        
        while (wardSelect.options.length > 0) {
            wardSelect.remove(0);
        }
        
        const defaultWardOption = document.createElement('option');
        defaultWardOption.value = '';
        defaultWardOption.textContent = 'Select Ward';
        wardSelect.appendChild(defaultWardOption);
        
        if (selectedLGA && deltaStateWards[selectedLGA]) {
            const wards = deltaStateWards[selectedLGA].sort();
            wards.forEach(ward => {
                const option = document.createElement('option');
                option.value = ward;
                option.textContent = ward;
                wardSelect.appendChild(option);
            });
            wardSelect.disabled = false;
        } else {
            wardSelect.disabled = true;
        }
    });

    wardSelect.disabled = true;

    // Photo preview
    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.add('has-image');
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.classList.remove('has-image');
            preview.src = '';
        }
    }

    // Form validation
    document.getElementById('enumeratorForm').addEventListener('submit', function(e) {
        const lga = document.getElementById('lga').value;
        const ward = document.getElementById('ward').value;
        
        if (!lga || !ward) {
            e.preventDefault();
            alert('Please select both LGA and Ward');
            return false;
        }
    });